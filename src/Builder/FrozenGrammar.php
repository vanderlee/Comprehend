<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Builder;

use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Comprehend;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\Utf8Source;

/** A reusable resolved snapshot; no mutable parser graph is exposed. */
final class FrozenGrammar
{
    private function __construct(private readonly Parser $root, public readonly array $analysisWarnings) {}

    public static function from(Comprehend|Parser $parser): self
    {
        if ($parser instanceof Comprehend) {
            $parser = $parser->parser();
        }
        $root = (new GraphSnapshot())->copy($parser);
        $warnings = (new GraphAnalysis())->validate($root);
        return new self($root, $warnings);
    }

    public function matchPrefix(
        Source|string $source,
        int $byteOffset = 0,
        ?ParseLimits $limits = null,
    ): ParseResult {
        return $this->root->matchPrefix(is_string($source) ? new Utf8Source($source) : $source, $byteOffset, $limits);
    }

    public function parseAll(
        Source|string $source,
        int $byteOffset = 0,
        ?ParseLimits $limits = null,
    ): ParseResult {
        return $this->root->parseAll(is_string($source) ? new Utf8Source($source) : $source, $byteOffset, $limits);
    }

    public function parse(Source|string $source, int $byteOffset = 0, ?ParseLimits $limits = null): ParseResult
    {
        return $this->parseAll($source, $byteOffset, $limits);
    }
}
