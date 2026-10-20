<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Optimizer;

use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\Choice as OptimizedChoice;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\Not as OptimizedNot;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\Repeat as OptimizedRepeat;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\Sequence as OptimizedSequence;
use Vanderlee\Comprehend\Builder\RuleReference;
use Vanderlee\Comprehend\Directive\CaseSensitive;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Failure;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Not;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Any;
use Vanderlee\Comprehend\Parser\Terminal\Char;
use Vanderlee\Comprehend\Parser\Terminal\Range;
use Vanderlee\Comprehend\Parser\Terminal\Regex;
use Vanderlee\Comprehend\Parser\Terminal\Set;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Tree\ConcreteTemplate;

/**
 * Classifies bounded lexical patterns through their original recognition runtime, then
 * reuses source-free syntax and diagnostic recipes. This is a bounded lexical table,
 * not position memoization. Callbacks, custom parsers and unproven lookahead execute normally.
 */
final class LexicalTable
{
    private const MAX_LOOKAHEAD = 32;
    private const MAX_ENTRIES = 128;

    /** @var array<string, array{int, ConcreteTemplate|null, ?int, list<string>, list<list<string>>}> */
    private array $entries = [];

    private function __construct(private readonly Parser $parser, private readonly int $lookahead)
    {
        // Misses continue through the original typed recognition graph.
    }

    public static function prepare(Parser $parser): ?self
    {
        $lookahead = self::lookahead($parser);

        return $lookahead !== null && $lookahead > 0 && $lookahead <= self::MAX_LOOKAHEAD
            ? new self($parser, $lookahead) : null;
    }

    /** @param array<int, true> $active */
    private static function lookahead(Parser $parser, array $active = []): ?int
    {
        if (isset($active[spl_object_id($parser)]) || count($active) > 16) {
            return null;
        }
        $class = match ($parser::class) {
            OptimizedRepeat::class => Repeat::class,
            OptimizedChoice::class => Choice::class,
            OptimizedNot::class => Not::class,
            OptimizedSequence::class => Sequence::class,
            default => $parser::class,
        };
        $supported = [Set::class, Char::class, Range::class, Text::class, Regex::class, Any::class,
            RuleReference::class, CaseSensitive::class, Sequence::class, Choice::class, Not::class, Repeat::class];
        if (!in_array($class, $supported, true) || $parser->hasExecutableMetadata()) {
            return null;
        }
        if (in_array($class, [Set::class, Char::class, Range::class, Text::class, Regex::class], true)) {
            $bound = $parser->lexicalLookahead();
            return $bound !== null && $bound <= self::MAX_LOOKAHEAD ? $bound : null;
        }
        if ($class === Any::class) {
            return 1;
        }
        if (($parser instanceof Sequence || $parser instanceof Repeat) && $parser->hasExplicitSpacing()) {
            return null;
        }
        $active[spl_object_id($parser)] = true;
        $bounds = [];
        foreach ($parser->children() as $child) {
            $bound = self::lookahead($child, $active);
            if ($bound === null) {
                return null;
            }
            $bounds[] = $bound;
        }
        if ($parser instanceof Repeat) {
            $max = $parser->getMaximum();
            return $max === null || $max > self::MAX_LOOKAHEAD ? null : ($bounds[0] ?? 0) * $max;
        }

        return $parser instanceof Sequence ? array_sum($bounds) : max($bounds ?: [0]);
    }

    /** @internal The owning combinator establishes its spacing and budget scopes first. */
    public function attempt(Source $source, int $offset, ParseSession $session): AbstractMatch
    {
        $prefix = $source->sliceBytes($offset, min($source->byteLength(), $offset + $this->lookahead));
        if (preg_match('/[\x80-\xff]/', $prefix) !== 0) {
            return $this->parser->recognizeInSession($source, $offset, $session);
        }
        $key = $source->mode()->value . ':' . (int) $session->isCaseSensitive()
            . ':' . $session->getPreference()->value . ':' . $prefix;
        $path = $session->rulePath();
        if (isset($this->entries[$key])) {
            [$length, $tree, $failure, $expected, $paths] = $this->entries[$key];
            $this->replayFailure($session, $offset, $path, $failure, $expected, $paths);
            $node = $tree?->instantiate($source, $offset);
            $match = $node === null ? new Failure($length) : new Success($length);
            $diagnostic = $session->diagnostic($node === null);
            $match->setSourceMetadata(
                $node->span ?? $source->span($offset, $offset),
                $diagnostic?->byteOffset,
                $diagnostic,
            );
            if ($node !== null) {
                $match->setConcreteNode($node);
            }

            return $match;
        }
        if (count($this->entries) >= self::MAX_ENTRIES) {
            return $this->parser->recognizeInSession($source, $offset, $session);
        }

        $diagnostics = $session->diagnosticState();
        // Isolate the pattern's observations from failures inherited from its caller.
        $session->restoreDiagnostics([null, [], [], $diagnostics[3], null]);
        try {
            $match = $this->parser->recognizeInSession($source, $offset, $session);
            $observed = $session->diagnostic();
        } finally {
            $session->restoreDiagnostics($diagnostics);
        }
        $failure = $observed === null ? null : $observed->byteOffset - $offset;
        $expected = $observed->expected ?? [];
        $paths = array_map(
            static fn(array $observedPath): array => array_slice($observedPath, count($path)),
            $observed->rulePaths ?? [],
        );
        $this->replayFailure($session, $offset, $path, $failure, $expected, $paths);
        $diagnostic = $session->diagnostic(!$match->accepted);
        if ($match->span !== null) {
            $match->setSourceMetadata($match->span, $diagnostic?->byteOffset, $diagnostic);
        }
        $this->entries[$key] = [$match->length,
            $match->node === null ? null : ConcreteTemplate::from($match->node, $offset), $failure, $expected, $paths];

        return $match;
    }

    /**
     * @param list<string> $path
     * @param list<string> $expected
     * @param list<list<string>> $paths
     */
    private function replayFailure(
        ParseSession $session,
        int $offset,
        array $path,
        ?int $failure,
        array $expected,
        array $paths,
    ): void {
        if ($failure === null) {
            return;
        }
        $paths = array_map(static fn(array $relative): array => [...$path, ...$relative], $paths);
        foreach ($expected ?: [null] as $expectation) {
            $session->recordFailure($offset + $failure, $expectation, $paths);
        }
    }
}
