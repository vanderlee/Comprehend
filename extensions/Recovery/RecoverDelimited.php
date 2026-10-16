<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Recovery;

use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Extensions\Binding\ValueSequence;
use Vanderlee\Comprehend\Source\Span;

/** Owns a delimited operand; on failure synchronizes through its matching nested close. */
class RecoverDelimited extends RecoverWith
{
    use ExactClassSnapshotReuseTrait;

    public function __construct(
        Parser $parser,
        Parser $open,
        Parser $close,
        ?Parser $ignore = null,
        bool $allowEof = false,
        ?int $maxSkippedBytes = null,
    ) {
        $probes = ['open' => $open, 'close' => $close];
        $hasIgnore = $ignore !== null;
        if ($ignore !== null) {
            $probes['ignore'] = $ignore;
        }
        parent::__construct(
            new ValueSequence(1, $open, $parser, $close),
            static function (RecoveryContext $context) use ($hasIgnore, $allowEof): ?Span {
                $opening = $context->probe('open', $context->start);
                if ($opening === null || $context->regionTo($opening->end) === null) {
                    return null;
                }
                $position = $opening->end;
                $depth = 1;
                while (true) {
                    if ($hasIgnore && ($ignored = $context->probe('ignore', $position)) !== null) {
                        if ($context->regionTo($ignored->end) === null) {
                            return null;
                        }
                        $position = $ignored->end;
                        continue;
                    }
                    $closing = $context->probe('close', $position);
                    if ($closing !== null) {
                        if (--$depth === 0) {
                            return $context->regionTo($closing->end);
                        }
                        if ($context->regionTo($closing->end) === null) {
                            return null;
                        }
                        $position = $closing->end;
                        continue;
                    }
                    $nested = $context->probe('open', $position);
                    if ($nested !== null) {
                        if ($context->regionTo($nested->end) === null) {
                            return null;
                        }
                        ++$depth;
                        $position = $nested->end;
                        continue;
                    }
                    $unit = $context->readUnit($position);
                    if ($unit === null) {
                        return $allowEof && $position === $context->source->byteLength()
                            ? $context->regionTo($position) : null;
                    }
                    $position = $unit->end;
                }
            },
            $probes,
            $maxSkippedBytes,
        );
    }
}
