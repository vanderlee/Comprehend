<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Numbers\Parser;

use InvalidArgumentException;
use RuntimeException;
use Vanderlee\Comprehend\Builder\AnalyzableParser;
use Vanderlee\Comprehend\Builder\DefaultAnalysisTrait;
use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Builder\NullabilityLookup;
use Vanderlee\Comprehend\Builder\ReusableSnapshot;
use Vanderlee\Comprehend\Extensions\Numbers\SignPolicy;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Terminal\DiagnosticExpectationTrait;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/** Recognizes decimal text, checking bounds with native finite PHP floating-point values. */
class FloatNumber extends Parser implements AnalyzableParser, ReusableSnapshot
{
    use DefaultAnalysisTrait;
    use ExactClassSnapshotReuseTrait;
    use DiagnosticExpectationTrait;

    public function __construct(
        private readonly ?float $minimum = 0.0,
        private readonly ?float $maximum = null,
        private readonly bool $allowLeadingZeros = false,
        private readonly bool $allowExponent = true,
        private readonly SignPolicy $sign = SignPolicy::MINUS_ONLY,
        private readonly bool $allowWholeNumbers = true,
        private readonly bool $strict = false,
    ) {
        if (($minimum !== null && !is_finite($minimum)) || ($maximum !== null && !is_finite($maximum))) {
            throw new InvalidArgumentException('Float bounds must be finite or null');
        }
        if ($minimum !== null && $maximum !== null && $minimum > $maximum) {
            throw new InvalidArgumentException('Maximum must be greater than minimum');
        }
    }

    public function analysisNullability(NullabilityLookup $lookup): ?bool
    {
        return false;
    }

    public function __toString(): string
    {
        return 'float' . ($this->minimum === null ? '<-INF' : '[' . $this->minimum)
            . ',' . ($this->maximum === null ? 'INF>' : $this->maximum . ']');
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $whole = $this->allowLeadingZeros ? '[0-9]+' : '(?:0|[1-9][0-9]*)';
        $exponent = '[eE][+-]?[0-9]+';
        $optionalExponent = $this->allowExponent ? '(?:' . $exponent . ')?' : '';
        if ($this->allowWholeNumbers) {
            $number = '(?:' . $whole . '(?:\.[0-9]*)?|\.[0-9]+)' . $optionalExponent;
        } else {
            // A decimal point or an enabled exponent distinguishes a float spelling.
            $decimal = '(?:' . $whole . '\.[0-9]*|\.[0-9]+)';
            $number = '(?:' . $decimal . $optionalExponent
                . ($this->allowExponent ? '|' . $whole . $exponent : '') . ')';
        }
        $body = $this->sign->pattern() . $number;
        $session->consumeWork(max(1, $source->byteLength()), $byteOffset);
        $subject = $session->regexSubject();
        $status = preg_match('/' . $body . '/A', $subject, $matches, 0, $byteOffset);
        if ($status === false) {
            throw new RuntimeException('Float recognition failed: ' . preg_last_error_msg());
        }
        if ($status !== 1) {
            return $this->failure($source, $byteOffset);
        }
        if ($this->strict) {
            $next = $subject[$byteOffset + strlen($matches[0])] ?? '';
            if ($next !== '' && str_contains('0123456789.eE', $next)) {
                return $this->failure($source, $byteOffset);
            }
        }

        // Bounds and overflow may select a shorter prefix, but it must remain a complete number.
        $candidate = $matches[0];
        while ($candidate !== '') {
            $session->consumeWork(strlen($candidate), $byteOffset);
            if (preg_match('/\A' . $body . '\z/', $candidate) === 1 && $this->allowsSignedZero($candidate)) {
                $value = (float) $candidate;
                if (
                    is_finite($value)
                    && ($this->minimum === null || $value >= $this->minimum)
                    && ($this->maximum === null || $value <= $this->maximum)
                ) {
                    return $this->numericSuccess($source, $byteOffset, strlen($candidate), $value);
                }
            }
            if ($this->strict) {
                break;
            }
            $candidate = substr($candidate, 0, -1);
        }

        return $this->failure($source, $byteOffset);
    }

    protected function numericSuccess(Source $source, int $offset, int $length, float $value): Success
    {
        return $this->success($source, $offset, $length);
    }

    private function allowsSignedZero(string $candidate): bool
    {
        // Test the spelling, not the native value: a tiny nonzero significand may underflow to zero.
        return $this->allowLeadingZeros
            || preg_match('/\A[+-](?:0+(?:\.0*)?|\.0+)(?:[eE][+-]?[0-9]+)?\z/', $candidate) !== 1;
    }
}
