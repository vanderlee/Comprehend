<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Recovery;

use InvalidArgumentException;
use LogicException;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Result\Diagnostic;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\Span;

/** Scoped, budgeted source access and named grammar probes for a pure recovery strategy. */
final class RecoveryContext
{
    private bool $active = true;

    /** @param array<string, Parser> $probes */
    public function __construct(
        public readonly Source $source,
        public readonly int $start,
        public readonly Diagnostic $failure,
        private readonly ParseSession $session,
        private readonly array $probes,
        private readonly ?int $maxSkippedBytes = null,
    ) {
        if ($session->source() !== $source || $failure->source !== $source) {
            throw new InvalidArgumentException('Recovery context must use one source');
        }
        if ($maxSkippedBytes !== null && $maxSkippedBytes < 0) {
            throw new InvalidArgumentException('Recovery skip limit cannot be negative');
        }
        $this->assertPosition($start);
    }

    /** @internal The context must not keep operating on a completed recognition session. */
    public function close(): void
    {
        $this->active = false;
    }

    /** @internal Isolate diagnostics without isolating work or runtime scopes. */
    public static function attempt(Parser $parser, Source $source, int $offset, ParseSession $session): AbstractMatch
    {
        $diagnostics = $session->diagnosticState();
        $session->restoreDiagnostics([null, [], [], $session->failureRevision()]);
        try {
            return $parser->recognizeInSession($source, $offset, $session);
        } finally {
            $session->restoreDiagnostics($diagnostics);
        }
    }

    /** Syntax only; zero-width successes are invalid and recovered probes do not synchronize. */
    public function probe(string $name, int $offset): ?Span
    {
        $this->assertPosition($offset);
        $parser = $this->probes[$name] ?? throw new InvalidArgumentException('Unknown recovery probe: ' . $name);
        if (!$this->withinLimit($offset)) {
            return null;
        }
        $match = self::attempt($parser, $this->source, $offset, $this->session);
        if (!$match instanceof Success || $match->recoveries() !== []) {
            return null;
        }
        if ($match->length === 0) {
            throw new LogicException('Recovery probes must consume input');
        }

        return $match->span;
    }

    /** Null denotes EOF or the skip limit; positions and spans remain byte coordinates. */
    public function readUnit(int $offset): ?Span
    {
        $this->assertPosition($offset);
        if (!$this->withinLimit($offset)) {
            return null;
        }
        $unit = $this->session->readSourceUnit($offset);

        return $unit !== null && $this->withinLimit($unit->end) ? $unit : null;
    }

    /** Charge work not already accounted for by probes or source-unit reads. */
    public function consumeWork(int $amount = 1): void
    {
        $this->assertActive();
        $this->session->consumeWork($amount, $this->start);
    }

    /** Decline empty or over-budget regions; malformed positions are strategy contract errors. */
    public function regionTo(int $end): ?Span
    {
        $this->assertPosition($end);

        return $end > $this->start && $this->withinLimit($end)
            ? $this->source->span($this->start, $end) : null;
    }

    /** Scan to a named boundary, skipping complete quoted/comment regions when configured. */
    public function scanUntil(
        string $boundary,
        bool $consumeBoundary = false,
        bool $allowEof = false,
        ?string $ignore = null,
    ): ?Span {
        $position = $this->start;
        while (true) {
            if ($ignore !== null && ($ignored = $this->probe($ignore, $position)) !== null) {
                if (!$this->withinLimit($ignored->end)) {
                    return null;
                }
                $position = $ignored->end;
                continue;
            }
            $found = $this->probe($boundary, $position);
            if ($found !== null) {
                return $this->regionTo($consumeBoundary ? $found->end : $position);
            }
            $unit = $this->readUnit($position);
            if ($unit === null) {
                return $allowEof && $position === $this->source->byteLength() ? $this->regionTo($position) : null;
            }
            $position = $unit->end;
        }
    }

    private function withinLimit(int $end): bool
    {
        return $this->maxSkippedBytes === null || $end - $this->start <= $this->maxSkippedBytes;
    }

    private function assertActive(): void
    {
        if (!$this->active) {
            throw new LogicException('Recovery context is no longer active');
        }
    }

    private function assertPosition(int $offset): void
    {
        $this->assertActive();
        if (
            $offset < $this->start || $offset > $this->source->byteLength()
            || !$this->source->isUnitBoundary($offset)
        ) {
            throw new LogicException('Recovery position must be a source-unit boundary at or after its start');
        }
    }
}
