<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Optimizer;

use LogicException;
use WeakMap;
use Vanderlee\Comprehend\Builder\GraphOptimization;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\Choice as OptimizedChoice;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\Not as OptimizedNot;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\Repeat as OptimizedRepeat;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\Sequence as OptimizedSequence;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\Trivia as OptimizedTrivia;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\SeparatedList as OptimizedSeparatedList;
use Vanderlee\Comprehend\Directive\Trivia;
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
use Vanderlee\Comprehend\Parser\Structure\SeparatedList;
use Vanderlee\Comprehend\Parser\Terminal\Any;
use Vanderlee\Comprehend\Parser\Terminal\Char;
use Vanderlee\Comprehend\Parser\Terminal\Range;
use Vanderlee\Comprehend\Parser\Terminal\Set;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;
use Vanderlee\Comprehend\Source\ByteFileSource;
use Vanderlee\Comprehend\Source\Utf8FileSource;
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
            Trivia::class => OptimizedTrivia::from($parser),
            SeparatedList::class => OptimizedSeparatedList::from($parser),
            Sequence::class => $parser->hasExplicitSpacing() || $parser->children() === []
                ? clone $parser : OptimizedSequence::from($parser),
            default => clone $parser,
        };
    }

    public function prepare(Parser $root): void
    {
        /** @var WeakMap<Parser, int> $uses */
        $uses = new WeakMap();
        $parsers = [];
        $pending = [$root];
        while ($pending !== []) {
            $parser = array_pop($pending);
            if (isset($uses[$parser])) {
                $uses[$parser] = $uses[$parser] + 1;
                continue;
            }
            $uses[$parser] = 1;
            $parsers[] = $parser;
            array_push($pending, ...$parser->children());
        }
        $preparation = new Preparation($uses);
        foreach ($parsers as $parser) {
            if (
                in_array($parser::class, [OptimizedRepeat::class, OptimizedChoice::class,
                    OptimizedNot::class, OptimizedSequence::class], true)
            ) {
                $parser->prepareRecognition($preparation);
            } elseif (in_array($parser::class, [OptimizedTrivia::class, OptimizedSeparatedList::class], true)) {
                $parser->prepareRecognition();
            }
            if (in_array($parser::class, [OptimizedRepeat::class, ReadWhileSet::class, ReadWhileRange::class], true)) {
                $parser->prepareTriviaRecognition();
            }
        }
    }

    public static function table(Parser $parser, ?Preparation $preparation = null): ?LexicalTable
    {
        return LexicalTable::prepare($parser, $preparation);
    }

    /** @return array<array-key, LexicalTable> */
    public static function choiceTables(Choice $choice, ?Preparation $preparation = null): array
    {
        $tables = [];
        foreach ($choice as $index => $parser) {
            $table = self::table($parser, $preparation);
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
        bool $retainFailureDiagnostic = true,
    ): AbstractMatch {
        return $table !== null && !$session->hasRecognitionLimits() && !$session->hasActiveSpacing()
            ? $table->attempt($source, $offset, $session, $retainFailureDiagnostic)
            : $parser->recognizeInSession($source, $offset, $session, $retainFailureDiagnostic);
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
        if (self::hasSingleByteUnits($operand, $source, $session)) {
            // Recognition already proved the run: retain each original child without decoding it again.
            $kind = $operand->concreteKind();
            for ($position = $offset; $position < $end; ++$position) {
                $children[] = new ConcreteNode($kind, $source->span($position, $position + 1));
            }

            return [$length, $children];
        }
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

    private static function hasSingleByteUnits(Parser $operand, Source $source, ParseSession $session): bool
    {
        if (in_array($source::class, [ByteSource::class, ByteFileSource::class], true)) {
            return true;
        }
        if (!in_array($source::class, [Utf8Source::class, Utf8FileSource::class], true)) {
            return false;
        }
        if (
            !$operand instanceof Text && !$operand instanceof Char
            && !$operand instanceof Set && !$operand instanceof Range
        ) {
            return false;
        }
        // Unicode case folding can match multibyte input against ASCII (for example Kelvin sign).
        if (!($operand->getCaseSensitivity() ?? $session->isCaseSensitive())) {
            return false;
        }

        return match ($operand::class) {
            Text::class => strlen($operand->getText()) === 1 && ord($operand->getText()) < 128,
            Char::class => $operand->includesCharacter() && strlen($operand->getCharacter()) === 1
                && ord($operand->getCharacter()) < 128,
            Set::class => $operand->includesCharacters()
                && preg_match('/[\x80-\xff]/', $operand->getCharacters()) === 0,
            Range::class => $operand->includesRange() && $operand->getLast() !== null
                && strlen($operand->getLast()) === 1 && ord($operand->getLast()) < 128,
            default => false,
        };
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
