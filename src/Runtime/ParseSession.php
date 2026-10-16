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
    /**
     * @var list<string>
     */
    private array $expectations = [];
    /**
     * @var list<list<string>>
     */
    private array $rulePaths = [];
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
        private readonly bool $trees = true,
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

    public function treesEnabled(): bool
    {
        return $this->trees;
    }

    /** @internal A bounded, session-owned full subject; large subjects retain uncached access. */
    public function regexSubject(): string
    {
        // A cached string must not make a closed file snapshot readable again.
        $this->source->isUnitBoundary(0);
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
        [
            $this->farthestFailure, $this->expectations, $this->rulePaths,
            $this->failureRevision, $this->deferredListFailure,
        ] = $state;
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
        $diagnostic = $this->farthestFailure === null
            ? null
            : new Diagnostic($this->source, $this->farthestFailure, $this->expectations, $this->rulePaths);

        return $includeDeferredLists ? $this->mergeDiagnostics($diagnostic, $this->deferredListFailure) : $diagnostic;
    }

    private function mergeDiagnostics(?Diagnostic $left, ?Diagnostic $right): ?Diagnostic
    {
        if ($left === null || ($right !== null && $right->byteOffset > $left->byteOffset)) {
            return $right;
        }
        if ($right === null || $left->byteOffset > $right->byteOffset) {
            return $left;
        }

        return new Diagnostic(
            $this->source,
            $left->byteOffset,
            array_values(array_unique(array_merge($left->expected, $right->expected))),
            array_values(array_unique(array_merge($left->rulePaths, $right->rulePaths), SORT_REGULAR)),
        );
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
        $this->source->span($byteOffset, $byteOffset);
        // Even a nearer failure counts as an observation for Parser's fallback diagnostic.
        ++$this->failureRevision;

        if ($this->farthestFailure === null || $byteOffset > $this->farthestFailure) {
            $this->farthestFailure = $byteOffset;
            $this->expectations = [];
            $this->rulePaths = [];
        }

        if ($byteOffset !== $this->farthestFailure) {
            return;
        }

        $this->mergeFailureDetails($expected, $paths);
    }

    /** @param list<list<string>>|null $paths */
    private function mergeFailureDetails(?string $expected, ?array $paths): void
    {
        if ($expected !== null && !in_array($expected, $this->expectations, true)) {
            $this->expectations[] = $expected;
        }

        foreach ($paths ?? [$this->path] as $path) {
            if (!in_array($path, $this->rulePaths, true)) {
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
