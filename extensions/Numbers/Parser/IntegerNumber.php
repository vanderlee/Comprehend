<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Numbers\Parser;

use Exception;
use InvalidArgumentException;
use Vanderlee\Comprehend\Extensions\Numbers\SignPolicy;
use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Builder\ReusableSnapshot;
use Vanderlee\Comprehend\Builder\AnalyzableParser;
use Vanderlee\Comprehend\Builder\DefaultAnalysisTrait;
use Vanderlee\Comprehend\Builder\NullabilityLookup;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Terminal\CaseSensitiveTrait;
use Vanderlee\Comprehend\Parser\Terminal\DiagnosticExpectationTrait;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\SourceMode;
use RuntimeException;

/**
 * Recognizes an integer representation with configurable base, digits and bounds. Checks
 * leading-zero policy, numeric range and overflow while honoring scoped case sensitivity.
 *
 * @author Martijn
 */
class IntegerNumber extends Parser implements AnalyzableParser, ReusableSnapshot
{
    use ExactClassSnapshotReuseTrait;
    use DefaultAnalysisTrait;
    use DiagnosticExpectationTrait;
    use CaseSensitiveTrait;

    /**
     * List of digits to use for the different bases (up to 36).
     *
     * @var string
     */
    public const DIGITS = '0123456789abcdefghijklmnopqrstuvwxyz';
    /** @var list<string> Symbols in increasing numeric order. */
    private array $digitSymbols;
    private bool $allowLeadingZeros;
    /**
     * @var int|null
     */
    private ?int $minimum;
    /**
     * @var int|null
     */
    private ?int $maximum;
    /**
     * @var int
     */
    private int $base;

    /**
     * @throws Exception
     */
    public function __construct(
        ?int $minimum = 0,
        ?int $maximum = null,
        int $base = 10,
        bool $allowLeadingZeros = false,
        string $digits = self::DIGITS,
        private readonly SignPolicy $sign = SignPolicy::MINUS_ONLY,
        private readonly bool $strict = false,
    ) {
        if ($minimum !== null && $maximum !== null && $minimum > $maximum) {
            throw new Exception('Maximum must be greater than minimum');
        }
        $this->minimum = $minimum;
        $this->maximum = $maximum;
        $this->base = $base;
        if (!mb_check_encoding($digits, 'UTF-8')) {
            throw new InvalidArgumentException('Digit alphabet must be valid UTF-8');
        }
        $symbols = mb_str_split($digits, 1, 'UTF-8');
        if (count(array_unique($symbols)) !== count($symbols) || in_array('-', $symbols, true)) {
            throw new InvalidArgumentException('Digit symbols must be unique and exclude the minus sign');
        }
        if (in_array($sign, [SignPolicy::OPTIONAL, SignPolicy::REQUIRED], true) && in_array('+', $symbols, true)) {
            throw new InvalidArgumentException('Digit alphabet conflicts with the plus sign policy');
        }
        if ($base < 2 || $base > count($symbols)) {
            throw new Exception('Unsupported base');
        }
        $this->digitSymbols = array_slice($symbols, 0, $base);
        $this->allowLeadingZeros = $allowLeadingZeros;
    }

    public function analysisNullability(NullabilityLookup $lookup): ?bool
    {
        return false;
    }

    public function __toString(): string
    {
        return ($this->minimum === null ? '<-INF' : '[' . $this->minimum)
            . ',' . ($this->maximum === null ? 'INF>' : $this->maximum . ']');
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $this->pushCaseSensitivityToContext($session);
        try {
            $caseSensitive = $session->isCaseSensitive();
            $values = $this->digitValues($caseSensitive);
            $pattern = $this->recognitionPattern($caseSensitive, $source->mode());
        } finally {
            $this->popCaseSensitivityFromContext($session);
        }
        $session->consumeWork(max(1, $source->byteLength()), $byteOffset);
        $subject = $session->regexSubject();
        $candidatePattern = $this->strict
            ? $this->recognitionPattern($caseSensitive, $source->mode(), true)
            : $pattern;
        $status = preg_match($candidatePattern, $subject, $match, 0, $byteOffset);
        if ($status === false) {
            throw new RuntimeException('Integer recognition failed: ' . preg_last_error_msg());
        }
        if ($status !== 1) {
            return $this->failure($source, $byteOffset);
        }
        if ($this->strict) {
            $session->consumeWork(strlen($match[0]), $byteOffset);
            if (preg_match($pattern, $match[0], $valid) !== 1 || strlen($valid[0]) !== strlen($match[0])) {
                return $this->failure($source, $byteOffset);
            }
        }

        // Try shorter prefixes when the full integer lies outside the bounds.
        $negative = str_starts_with($match[0], '-');
        $hasSign = $negative || (
            in_array($this->sign, [SignPolicy::OPTIONAL, SignPolicy::REQUIRED], true)
            && str_starts_with($match[0], '+')
        );
        $symbols = mb_str_split($hasSign ? substr($match[0], 1) : $match[0], 1, 'UTF-8');
        $length = strlen($match[0]);
        while ($symbols !== []) {
            $integer = $this->integerValue($symbols, $values, $negative, $caseSensitive, $session, $byteOffset);
            if ($integer !== null && $this->isInRange($integer)) {
                return $this->numericSuccess($source, $byteOffset, $length, $integer);
            }
            if ($this->strict) {
                break;
            }
            $length -= strlen(array_pop($symbols));
        }

        return $this->failure($source, $byteOffset);
    }

    protected function numericSuccess(Source $source, int $offset, int $length, int $value): Success
    {
        return $this->success($source, $offset, $length);
    }

    private function recognitionPattern(bool $caseSensitive, SourceMode $mode, bool $candidate = false): string
    {
        $all = self::digitPattern($this->digitSymbols);
        $nonzero = self::digitPattern(array_slice($this->digitSymbols, 1));
        $zero = preg_quote($this->digitSymbols[0], '/');
        $sign = $this->sign->pattern();
        // Without leading zeros, only unsigned zero is legal, including under REQUIRED.
        $unsignedZero = $this->sign === SignPolicy::REQUIRED ? '' : $zero . '|';
        $pattern = $this->allowLeadingZeros
            ? '/' . $sign . $all . '+/A'
            : '/(?:' . $unsignedZero . $sign . $nonzero . $all . '*)/A';
        if ($candidate) {
            // Include ASCII digits outside the base so strict binary 102 cannot stop at 10.
            $pattern = '/' . $sign . '(?:' . $all . '|[0-9])+/A';
        }
        $pattern .= $caseSensitive ? '' : 'i';
        $pattern .= $mode === SourceMode::UTF8 ? 'u' : '';

        return $pattern;
    }

    /**
     * @return array<array-key, int>
     */
    private function digitValues(bool $caseSensitive): array
    {
        $values = [];
        foreach ($this->digitSymbols as $value => $symbol) {
            $key = $caseSensitive ? $symbol : mb_convert_case($symbol, MB_CASE_FOLD, 'UTF-8');
            if (array_key_exists($key, $values)) {
                throw new InvalidArgumentException('Digit alphabet is ambiguous under case-insensitive matching');
            }
            $values[$key] = $value;
        }

        return $values;
    }

    /**
     * Alternatives keep multibyte symbols intact, including on byte sources.
     * @param list<string> $symbols
     */
    private static function digitPattern(array $symbols): string
    {
        $text = implode('', $symbols);
        if (strlen($text) === count($symbols)) {
            return '[' . preg_quote($text, '/') . ']';
        }

        $quoted = array_map(static fn(string $symbol): string => preg_quote($symbol, '/'), $symbols);

        return '(?:' . implode('|', $quoted) . ')';
    }

    /**
     * Accumulate negatively so PHP_INT_MIN remains representable; reject overflow.
     * @param list<string> $symbols
     * @param array<array-key, int> $values
     */
    private function integerValue(
        array $symbols,
        array $values,
        bool $negative,
        bool $caseSensitive,
        ParseSession $context,
        int $offset,
    ): ?int {
        $integer = 0;
        $limit = $negative ? PHP_INT_MIN : -PHP_INT_MAX;
        foreach ($symbols as $symbol) {
            $context->consumeWork(1, $offset);
            $key = $caseSensitive ? $symbol : mb_convert_case($symbol, MB_CASE_FOLD, 'UTF-8');
            if (!array_key_exists($key, $values)) {
                return null;
            }
            $digit = $values[$key];
            if ($integer < intdiv($limit + $digit, $this->base)) {
                return null;
            }
            $integer = $integer * $this->base - $digit;
        }

        return $negative ? $integer : -$integer;
    }

    private function isInRange(int $integer): bool
    {
        return ($this->minimum === null || $integer >= $this->minimum)
            && ($this->maximum === null || $integer <= $this->maximum);
    }
}
