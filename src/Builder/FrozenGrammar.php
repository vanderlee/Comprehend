<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Builder;

use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Source\Source;

/** A reusable resolved snapshot; no mutable parser graph is exposed. */
final readonly class FrozenGrammar
{
    private function __construct(private Parser $root, public array $analysisWarnings) {}

    public static function from(Parser $parser): self
    {
        $root = (new GraphSnapshot())->copy($parser);
        $warnings = (new GraphAnalysis())->validate($root);
        return new self($root, $warnings);
    }

    public function matchPrefix(Source $source, int $byteOffset = 0): ParseResult
    {
        return $this->root->matchPrefix($source, $byteOffset);
    }

    public function parseAll(Source $source, int $byteOffset = 0): ParseResult
    {
        return $this->root->parseAll($source, $byteOffset);
    }
}
