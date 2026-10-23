<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Optimizer;

use WeakMap;
use Vanderlee\Comprehend\Builder\RuleReference;
use Vanderlee\Comprehend\Directive\CaseSensitive;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\Choice as OptimizedChoice;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\Not as OptimizedNot;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\Repeat as OptimizedRepeat;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\Sequence as OptimizedSequence;
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
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Tree\ConcreteTemplate;

/** @internal Temporary, source-free preparation reuse within one resolved graph. */
final class Preparation
{
    public const MAX_LITERAL_BYTES = 65536;
    public const MAX_LITERAL_NODES = 4096;
    public const MAX_LOOKAHEAD = 32;

    /** @var WeakMap<Parser, array{string, int, int}> Literal, expanded nodes and structural height. */
    private WeakMap $literals;
    /** @var WeakMap<Parser, array{int, int}> Lookahead and structural height. */
    private WeakMap $lookaheads;
    /** @var WeakMap<Parser, array{int, ConcreteTemplate}> Byte length and relative syntax. */
    private WeakMap $templates;

    /** @param WeakMap<Parser, int>|null $uses Incoming graph occurrences, when known. */
    public function __construct(private readonly ?WeakMap $uses = null)
    {
        $this->literals = new WeakMap();
        $this->lookaheads = new WeakMap();
        $this->templates = new WeakMap();
    }

    /**
     * @param array<int, true> $active
     * @return array{string, int, int}|null Literal, expanded nodes and structural height.
     */
    public function literal(Parser $parser, array $active = []): ?array
    {
        $id = spl_object_id($parser);
        $depth = count($active);
        if (isset($active[$id]) || $depth >= 32 || $parser->hasExecutableMetadata()) {
            return null;
        }
        if ($parser::class === Text::class) {
            $text = $parser->getText();
            return $parser->getCaseSensitivity() !== false && strlen($text) <= self::MAX_LITERAL_BYTES
                && preg_match('/[\x80-\xff]/', $text) === 0
                ? [$text, 1, 1] : null;
        }
        if (isset($this->literals[$parser])) {
            $compiled = $this->literals[$parser];
            // Only successful, acyclic classifications are reused. A deeper caller must
            // still fit the original traversal bound; failures depend on their ancestry.
            return $depth + $compiled[2] <= 32 ? $compiled : null;
        }
        if (
            !in_array($parser::class, [Sequence::class, OptimizedSequence::class,
                Repeat::class, OptimizedRepeat::class, RuleReference::class], true)
            || (($parser instanceof Sequence || $parser instanceof Repeat) && $parser->hasExplicitSpacing())
        ) {
            return null;
        }
        $active[$id] = true;
        $literal = '';
        $nodes = 1;
        $height = 1;
        foreach ($parser->children() as $child) {
            $compiled = $this->literal($child, $active);
            if ($compiled === null) {
                return null;
            }
            $literal .= $compiled[0];
            $nodes += $compiled[1];
            $height = max($height, $compiled[2] + 1);
            if (strlen($literal) > self::MAX_LITERAL_BYTES || $nodes > self::MAX_LITERAL_NODES) {
                return null;
            }
        }
        if ($parser instanceof Repeat) {
            $count = $parser->getMaximum();
            if (
                $count === null || $count !== $parser->getMinimum()
                || $count > self::MAX_LITERAL_NODES || ($nodes - 1) * $count + 1 > self::MAX_LITERAL_NODES
                || strlen($literal) * $count > self::MAX_LITERAL_BYTES
            ) {
                return null;
            }
            $literal = str_repeat($literal, $count);
            $nodes = ($nodes - 1) * $count + 1;
        }

        return $this->literals[$parser] = [$literal, $nodes, $height];
    }

    /**
     * @param array<int, true> $active
     * @return array{int, int}|null Lookahead and structural height.
     */
    public function lookahead(Parser $parser, array $active = []): ?array
    {
        $id = spl_object_id($parser);
        $depth = count($active);
        if (isset($active[$id]) || $depth > 16) {
            return null;
        }
        if (isset($this->lookaheads[$parser])) {
            $compiled = $this->lookaheads[$parser];
            return $depth + $compiled[1] <= 17 ? $compiled : null;
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
            return $bound !== null && $bound <= self::MAX_LOOKAHEAD
                ? [$bound, 1] : null;
        }
        if ($class === Any::class) {
            return [1, 1];
        }
        if (($parser instanceof Sequence || $parser instanceof Repeat) && $parser->hasExplicitSpacing()) {
            return null;
        }
        $active[$id] = true;
        $bounds = [];
        $height = 1;
        foreach ($parser->children() as $child) {
            $compiled = $this->lookahead($child, $active);
            if ($compiled === null) {
                return null;
            }
            $bounds[] = $compiled[0];
            $height = max($height, $compiled[1] + 1);
        }
        if ($parser instanceof Repeat) {
            $max = $parser->getMaximum();
            if ($max === null || $max > self::MAX_LOOKAHEAD) {
                return null;
            }
            $bound = ($bounds[0] ?? 0) * $max;
        } else {
            $bound = $parser instanceof Sequence ? array_sum($bounds) : max($bounds ?: [0]);
        }

        return $this->lookaheads[$parser] = [$bound, $height];
    }

    private function isShared(Parser $parser): bool
    {
        return $this->uses === null || ($this->uses[$parser] ?? 0) > 1;
    }

    /**
     * Called only for proven callback-free ASCII literal operands. Cache relative syntax,
     * never the source, session or execution record used to construct it.
     * @return array{int, ConcreteTemplate}|null
     */
    public function literalTemplate(Parser $parser, Source $source, int $offset, ParseSession $session): ?array
    {
        $shared = $this->isShared($parser);
        if ($shared && isset($this->templates[$parser])) {
            return $this->templates[$parser];
        }
        $match = $parser->recognizeInSession($source, $offset, $session);
        if (!$match instanceof Success || $match->node === null) {
            return null;
        }

        $prepared = [$match->length, ConcreteTemplate::from($match->node, $offset)];
        if ($shared) {
            $this->templates[$parser] = $prepared;
        }

        return $prepared;
    }
}
