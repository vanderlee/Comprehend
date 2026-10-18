<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Library\Internal;

use Vanderlee\Comprehend\Builder\AnalyzableParser;
use Vanderlee\Comprehend\Builder\DefaultAnalysisTrait;
use Vanderlee\Comprehend\Builder\NullabilityLookup;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/** One non-ASCII UTF-8 scalar, including when the containing protocol uses octets. @internal */
final class Utf8NonAscii extends Parser implements AnalyzableParser
{
    use DefaultAnalysisTrait;

    public function __toString(): string
    {
        return 'UTF-8 non-ASCII scalar';
    }

    public function analysisNullability(NullabilityLookup $lookup): ?bool
    {
        return false;
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $session->consumeWork(1, $byteOffset);
        $first = $source->sliceBytes($byteOffset, min($byteOffset + 1, $source->byteLength()));
        $lead = $first === '' ? 0 : ord($first);
        $length = match (true) {
            $lead >= 0xc2 && $lead <= 0xdf => 2,
            $lead >= 0xe0 && $lead <= 0xef => 3,
            $lead >= 0xf0 && $lead <= 0xf4 => 4,
            default => 0,
        };
        if (
            $length === 0 || $byteOffset + $length > $source->byteLength()
            || !mb_check_encoding($source->sliceBytes($byteOffset, $byteOffset + $length), 'UTF-8')
        ) {
            return $this->failure($source, $byteOffset);
        }

        return $this->success($source, $byteOffset, $length);
    }
}
