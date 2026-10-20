<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Optimizer;

use LogicException;
use SplObjectStorage;
use Vanderlee\Comprehend\Builder\GraphOptimization;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\Choice as OptimizedChoice;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\Not as OptimizedNot;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\Repeat as OptimizedRepeat;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\Sequence as OptimizedSequence;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadRest;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadRun;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadUntil;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadWhile;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadWhileRange;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadWhileSet;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Not;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Any;
use Vanderlee\Comprehend\Parser\Terminal\Char;
use Vanderlee\Comprehend\Parser\Terminal\Range;
use Vanderlee\Comprehend\Parser\Terminal\Set;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Syntax\SyntaxExtension;
use Vanderlee\Comprehend\Tree\ConcreteNode;

/** Owns all optimized recognition decisions; ordinary parsers use the standard runtime. */
final class GraphOptimizer implements GraphOptimization, SyntaxExtension
{
    public function rules(): iterable
    {
        yield 'readWhile' => static fn(callable $predicate, int $min = 0, ?int $max = null): Parser
            => new ReadWhile($predicate, $min, $max);
        yield 'readUntil' => static fn(string|callable $delimiter, int $min = 0, ?int $max = null): Parser
            => new ReadUntil($delimiter, $min, $max);
        yield 'readWhileSet' => static fn(string $characters, int $min = 0, ?int $max = null): Parser
            => new ReadWhileSet($characters, $min, $max);
        yield 'readWhileRange' => static fn(?string $first, ?string $last, int $min = 0, ?int $max = null): Parser
            => new ReadWhileRange($first, $last, $min, $max);
        yield 'readRest' => static fn(): Parser => new ReadRest();
        yield 'skipWhile' => static fn(callable $predicate, int $min = 0, ?int $max = null): Parser
            => (new ReadWhile($predicate, $min, $max))->mapValue(static fn(): mixed => null);
        yield 'skipUntil' => static fn(string|callable $delimiter, int $min = 0, ?int $max = null): Parser
            => (new ReadUntil($delimiter, $min, $max))->mapValue(static fn(): mixed => null);
        yield 'skipRest' => static fn(): Parser => (new ReadRest())->mapValue(static fn(): mixed => null);
    }

    public function fluents(): iterable
    {
        return [];
    }

    public function replace(Parser $parser): Parser
    {
        return match ($parser::class) {
            Repeat::class => OptimizedRepeat::from($parser),
            Choice::class => OptimizedChoice::from($parser),
            Not::class => OptimizedNot::from($parser),
            Sequence::class => $parser->hasExplicitSpacing() || $parser->children() === []
                ? clone $parser : OptimizedSequence::from($parser),
            default => clone $parser,
        };
    }

    public function prepare(Parser $root): void
    {
        $seen = new SplObjectStorage();
        $pending = [$root];
        while ($pending !== []) {
            $parser = array_pop($pending);
            if ($seen->contains($parser)) {
                continue;
            }
            $seen->attach($parser);
            if (
                in_array($parser::class, [OptimizedRepeat::class, OptimizedChoice::class,
                    OptimizedNot::class, OptimizedSequence::class], true)
            ) {
                $parser->prepareRecognition();
            }
            array_push($pending, ...$parser->children());
        }
    }

    public static function table(Parser $parser): ?LexicalTable
    {
        return LexicalTable::prepare($parser);
    }

    /** @return array<array-key, LexicalTable> */
    public static function choiceTables(Choice $choice): array
    {
        $tables = [];
        foreach ($choice as $index => $parser) {
            $table = self::table($parser);
            if ($table !== null) {
                $tables[$index] = $table;
            }
        }

        return $tables;
    }

    public static function attempt(
        ?LexicalTable $table,
        Parser $parser,
        Source $source,
        int $offset,
        ParseSession $session,
    ): AbstractMatch {
        return $table !== null && !$session->hasRecognitionLimits() && !$session->hasActiveSpacing()
            ? $table->attempt($source, $offset, $session)
            : $parser->recognizeInSession($source, $offset, $session);
    }

    public static function scanner(Parser $parser, int $min, ?int $max): ?ReadRun
    {
        if ($max === 0) {
            return null;
        }

        return match ($parser::class) {
            Set::class => self::setScanner($parser, $min, $max),
            Range::class => self::rangeScanner($parser, $min, $max),
            Char::class => self::charScanner($parser, $min, $max),
            Text::class => self::textScanner($parser, $min, $max),
            Any::class => $parser->hasCompositionMetadata() ? null
                : (new ReadWhileRange("\0", null, $min, $max))->caseSensitive(),
            default => null,
        };
    }

    /** @return array{int, list<ConcreteNode>}|null Null requests ordinary repetition, including minimum failures. */
    public static function scanRepeat(
        ?ReadRun $scanner,
        Parser $operand,
        Source $source,
        int $offset,
        ParseSession $session,
    ): ?array {
        if ($scanner === null || $session->hasRecognitionLimits() || $session->hasActiveSpacing()) {
            return null;
        }
        [$accepted, $length] = $scanner->scanLength($source, $offset, $session);
        if (!$accepted) {
            return null;
        }
        $children = [];
        $end = $offset + $length;
        while ($offset < $end) {
            $unit = $source->readUnit($offset);
            if ($unit === null) {
                throw new LogicException('Scanned source unit is missing');
            }
            $children[] = new ConcreteNode($operand->concreteKind(), $unit);
            $offset = $unit->end;
        }

        return [$length, $children];
    }

    private static function textScanner(Text $parser, int $min, ?int $max): ?ReadRun
    {
        if (
            $parser->hasCompositionMetadata()
            || strlen($parser->getText()) !== 1 || ord($parser->getText()) > 127
        ) {
            return null;
        }

        return (new ReadWhileSet($parser->getText(), $min, $max))->setCaseSensitivity($parser->getCaseSensitivity());
    }

    private static function charScanner(Char $parser, int $min, ?int $max): ?ReadRun
    {
        if (
            $parser->hasCompositionMetadata()
            || strlen($parser->getCharacter()) !== 1 || ord($parser->getCharacter()) > 127
        ) {
            return null;
        }

        $scanner = $parser->includesCharacter()
            ? new ReadWhileSet($parser->getCharacter(), $min, $max)
            : new ReadUntil($parser->getCharacter(), $min, $max);

        return $scanner->setCaseSensitivity($parser->getCaseSensitivity());
    }

    private static function setScanner(Set $parser, int $min, ?int $max): ?ReadRun
    {
        if (!$parser->includesCharacters() || $parser->hasCompositionMetadata()) {
            return null;
        }

        return (new ReadWhileSet($parser->getCharacters(), $min, $max))
            ->setCaseSensitivity($parser->getCaseSensitivity());
    }

    private static function rangeScanner(Range $parser, int $min, ?int $max): ?ReadRun
    {
        if (!$parser->includesRange() || $parser->hasCompositionMetadata()) {
            return null;
        }
        foreach ([$parser->getFirst(), $parser->getLast()] as $bound) {
            if ($bound !== null && (strlen($bound) !== 1 || ord($bound) > 127)) {
                return null;
            }
        }
        if (
            $parser->getFirst() !== null && $parser->getLast() !== null
            && (ord($parser->getFirst()) > ord($parser->getLast())
                || ord(strtolower($parser->getFirst())) > ord(strtolower($parser->getLast())))
        ) {
            // Range treats reversed bounds as empty; ReadWhileRange rejects them.
            return null;
        }

        return (new ReadWhileRange($parser->getFirst(), $parser->getLast(), $min, $max))
            ->setCaseSensitivity($parser->getCaseSensitivity());
    }
}
