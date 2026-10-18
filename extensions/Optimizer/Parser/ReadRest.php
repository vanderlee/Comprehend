<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Optimizer\Parser;

use Vanderlee\Comprehend\Builder\AnalyzableParser;
use Vanderlee\Comprehend\Builder\DefaultAnalysisTrait;
use Vanderlee\Comprehend\Builder\NullabilityLookup;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\SourceMode;

/** Consumes the complete remaining source interval, including an empty remainder. */
final class ReadRest extends Parser implements AnalyzableParser
{
    use DefaultAnalysisTrait;

    public function analysisNullability(NullabilityLookup $lookup): ?bool
    {
        return true;
    }

    public function __toString(): string
    {
        return 'readRest()';
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $text = $source->sliceBytes($byteOffset, $source->byteLength());
        $units = $source->mode() === SourceMode::UTF8 ? mb_strlen($text, 'UTF-8') : strlen($text);
        $session->consumeLiteralWork($text, $units, $byteOffset);

        return $this->success($source, $byteOffset, strlen($text));
    }
}
