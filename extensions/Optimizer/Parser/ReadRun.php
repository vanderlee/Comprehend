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
use Vanderlee\Comprehend\Source\ByteFileSource;
use Vanderlee\Comprehend\Source\Utf8FileSource;
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
    private const FILE_CHUNK_BYTES = 8192;

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
        // Custom sources and scanners retain their observable unit-access hooks.
        $file = in_array($source::class, [ByteFileSource::class, Utf8FileSource::class], true)
            && in_array(static::class, [ReadUntil::class, ReadWhileSet::class, ReadWhileRange::class], true);
        if (
            $session->isCaseSensitive()
            && !$session->hasWorkLimit()
            && ($source instanceof InMemorySource || $file)
            && ($this->max === null || $this->max <= 65535)
        ) {
            $mode = $source->mode()->value . ($file ? ':file' : '');
            if (!array_key_exists($mode, $this->regexPatterns)) {
                $atom = $this->regexUnitPattern($source);
                // File chunks enforce the remaining maximum after matching. Avoid
                // expanding a finite quantifier into a potentially oversized PCRE program.
                $quantifier = $file || $this->max === null ? '*+' : '{0,' . $this->max . '}+';
                $this->regexPatterns[$mode] = $atom === null || strlen($atom) > self::MAX_REGEX_ATOM_BYTES ? null
                    : '~\\G(?:' . $atom . ')' . $quantifier . '~s'
                        . ($source->mode() === SourceMode::UTF8 ? 'u' : '');
            }
            $pattern = $this->regexPatterns[$mode];
            if ($pattern !== null) {
                $scan = $file
                    ? $this->scanFileRegex($pattern, $source, $byteOffset, $session)
                    : $this->scanRegex($pattern, $source, $byteOffset, $session);
                if ($scan !== null) {
                    return $scan;
                }
            }
        }

        return $this->scanUnits($source, $byteOffset, $session, $byteOffset, 0);
    }

    /** @return array{bool, int} */
    private function scanUnits(Source $source, int $start, ParseSession $session, int $end, int $count): array
    {
        while ($this->max === null || $count < $this->max) {
            $unit = $session->readSourceUnit($end);
            if ($unit === null || !$this->acceptsUnit($unit, $session)) {
                break;
            }
            $end = $unit->end;
            ++$count;
        }

        return [$count >= $this->min, $end - $start];
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

    /** Extra subject bytes needed to decide a unit at the end of a file chunk. */
    protected function regexLookaheadBytes(): int
    {
        return 0;
    }

    /** @return array{bool, int} */
    private function scanFileRegex(string $pattern, Source $source, int $offset, ParseSession $session): array
    {
        $end = $offset;
        $count = 0;
        $utf8 = $source->mode() === SourceMode::UTF8;
        while ($this->max === null || $count < $this->max) {
            $chunkEnd = min($source->byteLength(), $end + self::FILE_CHUNK_BYTES);
            while (!$source->isUnitBoundary($chunkEnd)) {
                --$chunkEnd;
            }
            // Lookahead is bounded by the generated atom limit. Include up to three
            // more bytes so retreating the subject end cannot truncate a delimiter.
            $subjectEnd = min($source->byteLength(), $chunkEnd + $this->regexLookaheadBytes() + 3);
            while (!$source->isUnitBoundary($subjectEnd)) {
                --$subjectEnd;
            }
            $subject = $source->sliceBytes($end, $subjectEnd);
            if (@preg_match($pattern, $subject, $matches) !== 1) {
                $this->regexPatterns[$source->mode()->value . ':file'] = null;

                // Earlier chunks have already charged their work; resume, do not replay them.
                return $this->scanUnits($source, $offset, $session, $end, $count);
            }
            $text = substr($matches[0], 0, $chunkEnd - $end);
            $units = $utf8 ? mb_strlen($text, 'UTF-8') : strlen($text);
            if ($this->max !== null && $units > $this->max - $count) {
                $units = $this->max - $count;
                $text = $utf8 ? mb_substr($text, 0, $units, 'UTF-8') : substr($text, 0, $units);
            }
            $next = $end + strlen($text);
            $count += $units;
            $limited = $this->max !== null && $count === $this->max;
            $stopped = $next < $chunkEnd || $next === $source->byteLength();
            $probe = !$limited && $stopped;
            $session->consumeLiteralWork($text, $units, $end);
            if ($probe) {
                $session->consumeWork(1, $next);
            }
            $this->chargeRegexLookahead($source, $end, $next, $probe, $session);
            $end = $next;
            if ($limited || $stopped) {
                break;
            }
        }

        return [$count >= $this->min, $end - $offset];
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
