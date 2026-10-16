<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Builder;

use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Comprehend;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\Utf8Source;

/**
 * Provides reusable parsing over a detached, resolved grammar snapshot. GraphSnapshot isolates
 * builder state and GraphAnalysis checks structural hazards before execution.
 */
final class FrozenGrammar
{
    private readonly bool $reusable;
    /**
     * @param list<string> $analysisWarnings
     */
    private function __construct(
        private readonly Parser $root,
        public readonly array $analysisWarnings,
        bool $reusable,
    ) {
        $this->reusable = $reusable;
    }

    public static function from(Comprehend|Parser $parser): self
    {
        if ($parser instanceof Comprehend) {
            $parser = $parser->parser();
        }
        $snapshot = new GraphSnapshot();
        $root = $snapshot->copy($parser);
        $warnings = (new GraphAnalysis())->validate($root);

        return new self($root, $warnings, $snapshot->isReusable());
    }

    /** @internal Check the original root too: freeze overrides must declare their own reuse guarantee. */
    public function canReuseFor(Parser $parser): bool
    {
        return $this->reusable && GraphSnapshot::isReusableParser($parser);
    }

    public function matchPrefix(
        Source|string $source,
        int $byteOffset = 0,
        ?ParseLimits $limits = null,
    ): ParseResult {
        return $this->root->matchPrefix(
            is_string($source) ? new Utf8Source($source) : $source,
            $byteOffset,
            $limits,
        );
    }

    public function parseAll(
        Source|string $source,
        int $byteOffset = 0,
        ?ParseLimits $limits = null,
    ): ParseResult {
        return $this->root->parseAll(
            is_string($source) ? new Utf8Source($source) : $source,
            $byteOffset,
            $limits,
        );
    }

    public function parse(Source|string $source, int $byteOffset = 0, ?ParseLimits $limits = null): ParseResult
    {
        return $this->parseAll($source, $byteOffset, $limits);
    }
}
