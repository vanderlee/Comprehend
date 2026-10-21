<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Optimizer\Parser;

use InvalidArgumentException;
use Vanderlee\Comprehend\Parser\Terminal\CaseSensitiveTrait;
use Vanderlee\Comprehend\Parser\Terminal\DiagnosticExpectationTrait;
use Vanderlee\Comprehend\Builder\AnalyzableParser;
use Vanderlee\Comprehend\Builder\DefaultAnalysisTrait;
use Vanderlee\Comprehend\Builder\NullabilityLookup;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
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
    use PreparedTriviaTrait;

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

    protected function copyDependencies(GraphSnapshot $context): void
    {
        parent::copyDependencies($context);
        $this->triviaPrepared = false;
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $this->pushCaseSensitivityToContext($session);
        try {
            [$accepted, $length] = $this->scanInContext($source, $byteOffset, $session);

            // Custom result-building hooks observe the scanner's effective case policy too.
            return $accepted
                ? $this->success($source, $byteOffset, $length)
                : $this->failure($source, $byteOffset, $length);
        } finally {
            $this->popCaseSensitivityFromContext($session);
        }
    }

    /**
     * @internal Shared scan primitive for optimizer-owned, metadata-free scanners.
     * The owning parser checks source positions; this primitive builds no match or tree.
     * @return array{bool, int} Acceptance and byte progress, including an unmet minimum.
     */
    final public function scanLength(Source $source, int $byteOffset, ParseSession $session): array
    {
        $this->pushCaseSensitivityToContext($session);
        try {
            return $this->scanInContext($source, $byteOffset, $session);
        } finally {
            $this->popCaseSensitivityFromContext($session);
        }
    }

    /** @return array{bool, int} */
    private function scanInContext(Source $source, int $byteOffset, ParseSession $session): array
    {
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
                $scan = $this->scanRegex($pattern, $source, $byteOffset, $session);
                if ($scan !== null) {
                    return $scan;
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

        return [$count >= $this->min, $end - $byteOffset];
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

    /** @return array{bool, int}|null Null requests the scalar scan. */
    private function scanRegex(string $pattern, Source $source, int $offset, ParseSession $session): ?array
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

        return [$count >= $this->min, strlen($text)];
    }

    abstract protected function acceptsUnit(Span $unit, ParseSession $session): bool;
}
