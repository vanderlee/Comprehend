<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Optimizer\Parser;

use InvalidArgumentException;
use Vanderlee\Comprehend\Parser\Terminal\CaseSensitiveTrait;
use Vanderlee\Comprehend\Parser\Terminal\DiagnosticExpectationTrait;
use Vanderlee\Comprehend\Builder\AnalyzableParser;
use Vanderlee\Comprehend\Builder\DefaultAnalysisTrait;
use Vanderlee\Comprehend\Builder\NullabilityLookup;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\InMemorySource;
use Vanderlee\Comprehend\Source\SourceMode;
use Vanderlee\Comprehend\Source\Span;

/** Shared bounded scanning with regex acceleration and a cooperative source-unit fallback. */
abstract class ReadRun extends Parser implements AnalyzableParser
{
    use DefaultAnalysisTrait;
    use DiagnosticExpectationTrait;
    use CaseSensitiveTrait;

    /** Keep generated classes and tempered literals comfortably below PCRE compilation limits. */
    private const MAX_REGEX_ATOM_BYTES = 8192;

    /** @var array<string, string|null> */
    private array $regexPatterns = [];

    public function __construct(private readonly int $min = 0, private readonly ?int $max = null)
    {
        if ($min < 0 || ($max !== null && $max < $min)) {
            throw new InvalidArgumentException('Read bounds require 0 <= min <= max');
        }
    }

    public function analysisNullability(NullabilityLookup $lookup): ?bool
    {
        return $this->min === 0;
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $this->pushCaseSensitivityToContext($session);
        try {
            $this->prepareScan($source, $session);
            if (
                $session->isCaseSensitive()
                && !$session->hasWorkLimit()
                && $source instanceof InMemorySource
                && ($this->max === null || $this->max <= 65535)
            ) {
                $mode = $source->mode()->value;
                if (!array_key_exists($mode, $this->regexPatterns)) {
                    $atom = $this->regexUnitPattern($source);
                    $quantifier = $this->max === null ? '*+' : '{0,' . $this->max . '}+';
                    $this->regexPatterns[$mode] = $atom === null || strlen($atom) > self::MAX_REGEX_ATOM_BYTES ? null
                        : '~\\G(?:' . $atom . ')' . $quantifier . '~s'
                            . ($source->mode() === SourceMode::UTF8 ? 'u' : '');
                }
                $pattern = $this->regexPatterns[$mode];
                if ($pattern !== null) {
                    $match = $this->recognizeRegex($pattern, $source, $byteOffset, $session);
                    if ($match !== null) {
                        return $match;
                    }
                }
            }
            $end = $byteOffset;
            $count = 0;
            while ($this->max === null || $count < $this->max) {
                $unit = $session->readSourceUnit($end);
                if ($unit === null || !$this->acceptsUnit($unit, $session)) {
                    break;
                }
                $end = $unit->end;
                ++$count;
            }

            return $count >= $this->min
                ? $this->success($source, $byteOffset, $end - $byteOffset)
                : $this->failure($source, $byteOffset, $end - $byteOffset);
        } finally {
            $this->popCaseSensitivityFromContext($session);
        }
    }

    protected function prepareScan(Source $source, ParseSession $session): void
    {
        // Predicate scanners have no source-dependent configuration.
    }

    /** A generated PCRE atom consuming exactly one unit, or null for callback scanners. */
    protected function regexUnitPattern(Source $source): ?string
    {
        return null;
    }

    /** @internal Literal-delimiter scanners also account for inspected lookahead bytes. */
    protected function chargeRegexLookahead(
        Source $source,
        int $start,
        int $end,
        bool $probe,
        ParseSession $session,
    ): void {
        // Set and range scanners do not inspect beyond the current unit.
    }

    private function recognizeRegex(string $pattern, Source $source, int $offset, ParseSession $session): ?AbstractMatch
    {
        // Generated classes/literals can exceed PCRE limits. The scalar path remains authoritative.
        $status = @preg_match($pattern, $session->regexSubject(), $matches, 0, $offset);
        if ($status !== 1) {
            $this->regexPatterns[$source->mode()->value] = null;
            return null;
        }
        $text = $matches[0];
        $count = $source->mode() === SourceMode::UTF8 ? mb_strlen($text, 'UTF-8') : strlen($text);
        $end = $offset + strlen($text);
        $probe = $this->max === null || $count < $this->max;
        $session->consumeLiteralWork($text, $count, $offset);
        if ($probe) {
            $session->consumeWork(1, $end);
        }
        $this->chargeRegexLookahead($source, $offset, $end, $probe, $session);

        return $count >= $this->min
            ? $this->success($source, $offset, strlen($text))
            : $this->failure($source, $offset, strlen($text));
    }

    abstract protected function acceptsUnit(Span $unit, ParseSession $session): bool;
}
