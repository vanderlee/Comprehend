<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Optimizer;

use Vanderlee\Comprehend\Builder\RuleReference;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\Repeat as OptimizedRepeat;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\Sequence as OptimizedSequence;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Tree\ConcreteTemplate;

/** @internal One literal recognizer with source-free recipes for the original child trees. */
final class LiteralSequence
{
    private const MAX_BYTES = 65536;
    private const MAX_NODES = 4096;

    /** @param list<ConcreteTemplate> $children */
    private function __construct(private Text $terminal, private array $children)
    {
        // Prepared once for a frozen graph; parsing instantiates fresh source-bound nodes.
    }

    public static function prepare(Sequence $sequence): ?self
    {
        $literal = '';
        $nodes = 0;
        foreach ($sequence as $child) {
            $compiled = self::compile($child);
            if ($compiled === null) {
                return null;
            }
            $literal .= $compiled[0];
            $nodes += $compiled[1];
            if (strlen($literal) > self::MAX_BYTES || $nodes > self::MAX_NODES) {
                return null;
            }
        }
        if ($literal === '') {
            return null;
        }

        // Only proven callback-free ASCII literals reach this classification parse.
        // Discard its source and execution records; templates retain static tree metadata only.
        $source = new ByteSource($literal);
        $session = new ParseSession($source);
        $offset = 0;
        $children = [];
        foreach ($sequence as $child) {
            $match = $child->recognizeInSession($source, $offset, $session);
            if (!$match instanceof Success || $match->node === null) {
                return null;
            }
            $children[] = ConcreteTemplate::from($match->node, 0);
            $offset += $match->length;
        }

        return new self((new Text($literal))->caseSensitive(), $children);
    }

    /**
     * @param array<int, true> $active
     * @return array{string, int}|null Literal and expanded node count.
     */
    private static function compile(Parser $parser, array $active = []): ?array
    {
        $id = spl_object_id($parser);
        if (isset($active[$id]) || count($active) >= 32 || $parser->hasExecutableMetadata()) {
            return null;
        }
        if ($parser::class === Text::class) {
            $text = $parser->getText();

            return $parser->getCaseSensitivity() !== false && strlen($text) <= self::MAX_BYTES
                && preg_match('/[\x80-\xff]/', $text) === 0 ? [$text, 1] : null;
        }
        if (
            !in_array($parser::class, [Sequence::class, OptimizedSequence::class,
                Repeat::class, OptimizedRepeat::class, RuleReference::class], true)
        ) {
            return null;
        }
        if (($parser instanceof Sequence || $parser instanceof Repeat) && $parser->hasExplicitSpacing()) {
            return null;
        }
        $active[$id] = true;
        $literal = '';
        $nodes = 1;
        foreach ($parser->children() as $child) {
            $compiled = self::compile($child, $active);
            if ($compiled === null) {
                return null;
            }
            $literal .= $compiled[0];
            $nodes += $compiled[1];
            if (strlen($literal) > self::MAX_BYTES || $nodes > self::MAX_NODES) {
                return null;
            }
        }
        if ($parser instanceof Repeat) {
            $count = $parser->getMaximum();
            if (
                $count === null || $count !== $parser->getMinimum()
                || $count > self::MAX_NODES || ($nodes - 1) * $count + 1 > self::MAX_NODES
                || strlen($literal) * $count > self::MAX_BYTES
            ) {
                return null;
            }
            $literal = str_repeat($literal, $count);
            $nodes = ($nodes - 1) * $count + 1;
        }

        return [$literal, $nodes];
    }

    /** @return list<Success>|null Null requests the original recognition and diagnostics. */
    public function attempt(Source $source, int $offset, ParseSession $session): ?array
    {
        if ($session->hasRecognitionLimits() || $session->hasActiveSpacing() || !$session->isCaseSensitive()) {
            return null;
        }
        $diagnostics = $session->diagnosticState();
        try {
            $match = $this->terminal->recognizeInSession($source, $offset, $session);
        } finally {
            $session->restoreDiagnostics($diagnostics);
        }
        if (!$match instanceof Success) {
            return null;
        }
        $diagnostic = $session->diagnostic();
        $children = [];
        foreach ($this->children as $template) {
            $node = $template->instantiate($source, $offset);
            $child = new Success($node->span->byteLength());
            $child->setSourceMetadata($node->span, $diagnostic?->byteOffset, $diagnostic);
            $child->setConcreteNode($node);
            $children[] = $child;
        }

        return $children;
    }
}
