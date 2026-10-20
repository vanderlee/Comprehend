<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Runtime;

use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Result\Diagnostic;
use Vanderlee\Comprehend\Runtime\Scope\CaseSensitiveContextTrait;
use Vanderlee\Comprehend\Runtime\Scope\PreferContextTrait;
use Vanderlee\Comprehend\Runtime\Scope\SpacingContextTrait;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\Span;
use Vanderlee\Comprehend\Source\SourceMode;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;
use InvalidArgumentException;
use LogicException;

/**
 * Owns the context of one recognition run over a Source. Coordinates scoped policies,
 * diagnostics, recursion protection, optional budgets and metrics for all participating
 * parsers.
 *
 * @author Martijn
 */
final class ParseSession
{
    use CaseSensitiveContextTrait;
    use PreferContextTrait;
    use SpacingContextTrait;

    private readonly Source $source;
    private ?int $farthestFailure = null;
    private ?Diagnostic $deferredListFailure = null;
    private ?Diagnostic $diagnosticCache = null;
    private ?Diagnostic $combinedDiagnosticCache = null;
    private ?Diagnostic $combinedDiagnosticActive = null;
    private ?Diagnostic $combinedDiagnosticDeferred = null;
    /**
     * @var list<string>
     */
    private array $expectations = [];
    /**
     * @var list<list<string>>
     */
    private array $rulePaths = [];
    /** @var array<string, true>|null Rebuilt lazily after a diagnostic checkpoint is restored. */
    private ?array $expectationIndex = [];
    /** @var array<string, true>|null */
    private ?array $rulePathIndex = [];
    /**
     * @var list<string>
     */
    private array $path = [];
    private int $failureRevision = 0;
    private int $lexemeDepth = 0;
    /**
     * @var array<int, array<int, true>>
     */
    private array $activeParsers = [];
    private int $work = 0;
    private int $depth = 0;
    private readonly ParseLimits $limits;
    private ?string $regexSubject = null;
    private static ?ParseLimits $defaultLimits = null;

    public function __construct(
        Source $source,
        ?Parser $trivia = null,
        bool $caseSensitive = true,
        ChoicePolicy $preference = ChoicePolicy::FIRST,
        ?ParseLimits $limits = null,
        private readonly ?ParseMetrics $metrics = null,
    ) {
        $this->source = $source;
        $this->limits = $limits ?? (self::$defaultLimits ??= new ParseLimits());
        $this->spacers = [$trivia];
        $this->caseSensitivityStack = [$caseSensitive];
        $this->preferenceStack = [$preference];
    }

    public function workUsed(): int
    {
        return $this->work;
    }

    /** @internal Bulk scanners retain cooperative scalar recognition under a finite work budget. */
    public function hasWorkLimit(): bool
    {
        return $this->limits->maxWork !== null;
    }

    /** @internal Rewrites must preserve budget positions and parser paths when limits are active. */
    public function hasRecognitionLimits(): bool
    {
        return $this->limits->maxWork !== null || $this->limits->maxDepth !== null;
    }

    /** @internal A bounded, session-owned full subject; large subjects retain uncached access. */
    public function regexSubject(): string
    {
        // A cached string must not make a closed file snapshot readable again.
        if (!$this->source instanceof ByteSource && !$this->source instanceof Utf8Source) {
            $this->source->isUnitBoundary(0);
        }
        if (
            $this->limits->maxRegexSubjectBytes === 0
            || $this->source->byteLength() > $this->limits->maxRegexSubjectBytes
        ) {
            return $this->source->sliceBytes(0, $this->source->byteLength());
        }

        return $this->regexSubject ??= $this->source->sliceBytes(0, $this->source->byteLength());
    }

    public function enterParser(Parser $parser, int $offset): void
    {
        $id = spl_object_id($parser);
        if (isset($this->activeParsers[$id][$offset])) {
            throw new LogicException(
                'Left recursion at byte ' . $offset . ': ' . implode(' -> ', $this->path)
                . ' -> ' . $parser->graphLabel(),
            );
        }
        if ($this->limits->maxDepth !== null && $this->depth >= $this->limits->maxDepth) {
            throw new ParseLimitException(
                'depth',
                $offset,
                [...$this->path, $parser->graphLabel()],
                $this->limits->maxDepth,
            );
        }

        $this->consumeWork(1, $offset);
        $this->metrics?->record($parser, $offset);

        // Only successful entry owns a matching leaveParser() call.
        ++$this->depth;
        $this->activeParsers[$id][$offset] = true;
    }

    public function consumeWork(int $amount = 1, int $byteOffset = 0): void
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Work increment must not be negative');
        }
        if ($this->limits->maxWork !== null && $amount > $this->limits->maxWork - $this->work) {
            throw new ParseLimitException('work', $byteOffset, $this->path, $this->limits->maxWork);
        }

        $this->work += $amount;
    }

    public function leaveParser(Parser $parser, int $offset): void
    {
        unset($this->activeParsers[spl_object_id($parser)][$offset]);
        --$this->depth;
    }

    /** @internal Charge validated literal units, retaining partial work and precise limit positions. */
    public function consumeLiteralWork(string $text, int $units, int $byteOffset): void
    {
        $remaining = $this->limits->maxWork === null ? null : $this->limits->maxWork - $this->work;
        if ($remaining !== null && $units > $remaining) {
            $prefixBytes = $this->source->mode() === SourceMode::UTF8
                ? strlen(mb_substr($text, 0, $remaining, 'UTF-8'))
                : $remaining;
            $this->work += $remaining;
            $this->consumeWork(1, $byteOffset + $prefixBytes);
        }
        $this->consumeWork($units, $byteOffset);
    }

    public function pushLexeme(): void
    {
        ++$this->lexemeDepth;
    }

    public function popLexeme(): void
    {
        --$this->lexemeDepth;
    }

    public function isLexeme(): bool
    {
        return $this->lexemeDepth > 0;
    }

    public function pushRule(string $rule): void
    {
        $this->path[] = $rule;
    }

    public function popRule(): void
    {
        array_pop($this->path);
    }

    /**
     * @internal Diagnostic templates retain relative paths, never their original caller's scope.
     * @return list<string>
     */
    public function rulePath(): array
    {
        return $this->path;
    }

    public function failureRevision(): int
    {
        return $this->failureRevision;
    }

    /**
     * @return array{int|null, list<string>, list<list<string>>, int, Diagnostic|null}
     */
    public function diagnosticState(): array
    {
        return [
            $this->farthestFailure, $this->expectations, $this->rulePaths,
            $this->failureRevision, $this->deferredListFailure,
        ];
    }

    /**
     * @param array{int|null, list<string>, list<list<string>>, int, Diagnostic|null} $state
     */
    public function restoreDiagnostics(array $state): void
    {
        if ($state[1] !== $this->expectations) {
            $this->expectationIndex = null;
        }
        if ($state[2] !== $this->rulePaths) {
            $this->rulePathIndex = null;
        }
        if (
            $state[0] !== $this->farthestFailure
            || $state[1] !== $this->expectations
            || $state[2] !== $this->rulePaths
        ) {
            $this->diagnosticCache = null;
        }
        [
            $this->farthestFailure, $this->expectations, $this->rulePaths,
            $this->failureRevision, $this->deferredListFailure,
        ] = $state;
    }

    /**
     * @internal Replace only this scope's entry failure; preceding alternatives remain intact.
     * @param array{int|null, list<string>, list<list<string>>, int, Diagnostic|null} $checkpoint
     */
    public function relabelFailure(int $offset, string $label, array $checkpoint): bool
    {
        if (
            ($this->farthestFailure !== null && $this->farthestFailure > $offset)
            || ($this->deferredListFailure !== null && $this->deferredListFailure->byteOffset > $offset)
        ) {
            return false;
        }
        $this->restoreDiagnostics($checkpoint);
        $this->recordFailure($offset, $label);

        return true;
    }

    /**
     * Roll back list syntax probes while keeping their failure available to a failed enclosing parse.
     * @param array{int|null, list<string>, list<list<string>>, int, Diagnostic|null} $state
     */
    public function deferListDiagnostics(array $state): void
    {
        $failure = $this->diagnostic(true);
        $this->restoreDiagnostics($state);
        $this->deferredListFailure = $this->mergeDiagnostics($this->deferredListFailure, $failure);
    }

    public function diagnostic(bool $includeDeferredLists = false): ?Diagnostic
    {
        $diagnostic = null;
        if ($this->farthestFailure !== null) {
            $diagnostic = $this->diagnosticCache ??= new Diagnostic(
                $this->source,
                $this->farthestFailure,
                $this->expectations,
                $this->rulePaths,
            );
        }

        if (
            !$includeDeferredLists
            || $this->deferredListFailure === null
        ) {
            return $diagnostic;
        }

        if (
            $this->combinedDiagnosticActive !== $diagnostic
            || $this->combinedDiagnosticDeferred !== $this->deferredListFailure
        ) {
            $this->combinedDiagnosticActive = $diagnostic;
            $this->combinedDiagnosticDeferred = $this->deferredListFailure;
            $this->combinedDiagnosticCache = $this->mergeDiagnostics($diagnostic, $this->deferredListFailure);
        }

        return $this->combinedDiagnosticCache;
    }

    private function mergeDiagnostics(?Diagnostic $left, ?Diagnostic $right): ?Diagnostic
    {
        if ($left === null || ($right !== null && $right->byteOffset > $left->byteOffset)) {
            return $right;
        }

        if ($right === null || $left->byteOffset > $right->byteOffset) {
            return $left;
        }

        $expected = array_values(array_unique(array_merge($left->expected, $right->expected)));
        $paths = array_values(array_unique(array_merge($left->rulePaths, $right->rulePaths), SORT_REGULAR));

        if ($expected === $left->expected && $paths === $left->rulePaths) {
            return $left;
        }

        if ($expected === $right->expected && $paths === $right->rulePaths) {
            return $right;
        }

        return new Diagnostic($this->source, $left->byteOffset, $expected, $paths);
    }

    public function source(): Source
    {
        return $this->source;
    }

    /**
     * @param list<list<string>>|null $paths
     */
    public function recordFailure(int $byteOffset, ?string $expected = null, ?array $paths = null): void
    {
        Span::assertByteOffset($this->source, $byteOffset);
        // Even a nearer failure counts as an observation for Parser's fallback diagnostic.
        ++$this->failureRevision;

        if ($this->farthestFailure === null || $byteOffset > $this->farthestFailure) {
            $this->diagnosticCache = null;
            $this->farthestFailure = $byteOffset;
            $this->expectations = [];
            $this->rulePaths = [];
            $this->expectationIndex = [];
            $this->rulePathIndex = [];
        }

        if ($byteOffset !== $this->farthestFailure) {
            return;
        }

        $this->mergeFailureDetails($expected, $paths);
    }

    /** @param list<list<string>>|null $paths */
    private function mergeFailureDetails(?string $expected, ?array $paths): void
    {
        if ($expected !== null) {
            if ($this->expectationIndex === null) {
                $this->expectationIndex = [];
                foreach ($this->expectations as $expectation) {
                    $this->expectationIndex['expected:' . $expectation] = true;
                }
            }
            $key = 'expected:' . $expected;
            if (!isset($this->expectationIndex[$key])) {
                $this->diagnosticCache = null;
                $this->expectationIndex[$key] = true;
                $this->expectations[] = $expected;
            }
        }

        if ($this->rulePathIndex === null) {
            $this->rulePathIndex = [];
            foreach ($this->rulePaths as $path) {
                $this->rulePathIndex[serialize($path)] = true;
            }
        }
        foreach ($paths ?? [$this->path] as $path) {
            // Serialization preserves strict path identity, including embedded delimiters and bytes.
            $key = serialize($path);
            if (!isset($this->rulePathIndex[$key])) {
                $this->diagnosticCache = null;
                $this->rulePathIndex[$key] = true;
                $this->rulePaths[] = $path;
            }
        }
    }

    public function farthestFailure(): ?int
    {
        return $this->farthestFailure;
    }

    public function readSourceUnit(int $byteOffset): ?Span
    {
        $this->consumeWork(1, $byteOffset);

        return $this->source->readUnit($byteOffset);
    }
}
