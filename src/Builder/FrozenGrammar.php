<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Builder;

use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Comprehend;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\MemoizationProfile;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\Utf8Source;
use Vanderlee\Comprehend\Syntax\ExtensionLoader;

/**
 * Provides reusable parsing over a detached, resolved grammar snapshot. GraphSnapshot isolates
 * builder state and GraphAnalysis checks structural hazards before execution.
 * @template-covariant T = mixed
 */
final readonly class FrozenGrammar
{
    private bool $reusable;
    /**
     * @param list<string> $analysisWarnings
     */
    private function __construct(
        private Parser $root,
        public array $analysisWarnings,
        bool $reusable,
    ) {
        $this->reusable = $reusable;
    }

    public static function from(Comprehend|Parser $parser, int $flags = FreezeFlags::DEFAULT): self
    {
        FreezeFlags::validate($flags);
        if ($parser instanceof Comprehend) {
            $parser = $parser->parser();
        }
        $optimizer = ($flags & FreezeFlags::OPTIMIZE) !== 0
            ? ExtensionLoader::defaults()->requireOne(GraphOptimization::class) : null;
        $snapshot = new GraphSnapshot(optimize: $optimizer !== null, optimizer: $optimizer);
        $root = $snapshot->copy($parser);
        $warnings = (new GraphAnalysis())->validate($root);
        $optimizer?->prepare($root);

        return new self($root, $warnings, $snapshot->isReusable());
    }

    /** @internal Check the original root too: freeze overrides must declare their own reuse guarantee. */
    public function canReuseFor(Parser $parser): bool
    {
        return $this->reusable && GraphSnapshot::isReusableParser($parser);
    }

    /** @return ParseResult<T> */
    public function matchPrefix(
        Source|string $source,
        int $byteOffset = 0,
        ?ParseLimits $limits = null,
        ?MemoizationProfile $memoizationProfile = null,
    ): ParseResult {
        $memoizationProfile?->registerFrozenGraph($this->root);
        return $this->root->matchPrefix(
            is_string($source) ? new Utf8Source($source) : $source,
            $byteOffset,
            $limits,
            $memoizationProfile,
        );
    }

    /** @return ParseResult<T> */
    public function parseAll(
        Source|string $source,
        int $byteOffset = 0,
        ?ParseLimits $limits = null,
        ?MemoizationProfile $memoizationProfile = null,
    ): ParseResult {
        $memoizationProfile?->registerFrozenGraph($this->root);
        return $this->root->parseAll(
            is_string($source) ? new Utf8Source($source) : $source,
            $byteOffset,
            $limits,
            $memoizationProfile,
        );
    }

    /** @return ParseResult<T> */
    public function parse(
        Source|string $source,
        int $byteOffset = 0,
        ?ParseLimits $limits = null,
        ?MemoizationProfile $memoizationProfile = null,
    ): ParseResult {
        return $this->parseAll($source, $byteOffset, $limits, $memoizationProfile);
    }
}
