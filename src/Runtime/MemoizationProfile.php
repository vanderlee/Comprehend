<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Runtime;

use InvalidArgumentException;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use WeakMap;

/**
 * Opt-in repeated-recognition research, never a recognition cache.
 *
 * @phpstan-type Observation array{signature: string, visits: int}
 * @phpstan-type Frame array{class: string, identity: int, offset: int, policy: ?string, strict: ?string, start: int,
 *     eligible: bool, policyCovered: int, strictCovered: int}
 * @phpstan-type Trace array{frames: list<Frame>, skipped: int}
 */
final class MemoizationProfile
{
    /** Exact, inspected built-ins only; callbacks and unknown descendants disqualify a subtree. */
    private const PURE_CLASSES = [
        'Vanderlee\\Comprehend\\Parser\\Terminal\\Text',
        'Vanderlee\\Comprehend\\Parser\\Terminal\\Char',
        'Vanderlee\\Comprehend\\Parser\\Terminal\\Range',
        'Vanderlee\\Comprehend\\Parser\\Terminal\\Set',
        'Vanderlee\\Comprehend\\Parser\\Terminal\\Any',
        'Vanderlee\\Comprehend\\Parser\\Terminal\\Regex',
        'Vanderlee\\Comprehend\\Parser\\Terminal\\Nothing',
        'Vanderlee\\Comprehend\\Parser\\Terminal\\Start',
        'Vanderlee\\Comprehend\\Parser\\Terminal\\End',
        'Vanderlee\\Comprehend\\Parser\\Structure\\Sequence',
        'Vanderlee\\Comprehend\\Parser\\Structure\\Choice',
        'Vanderlee\\Comprehend\\Parser\\Structure\\Repeat',
        'Vanderlee\\Comprehend\\Parser\\Structure\\Not',
        'Vanderlee\\Comprehend\\Parser\\Structure\\All',
        'Vanderlee\\Comprehend\\Parser\\Structure\\Except',
        'Vanderlee\\Comprehend\\Parser\\Structure\\SeparatedList',
        'Vanderlee\\Comprehend\\Directive\\CaseSensitive',
        'Vanderlee\\Comprehend\\Directive\\Prefer',
        'Vanderlee\\Comprehend\\Directive\\Lexeme',
        'Vanderlee\\Comprehend\\Directive\\Space',
        'Vanderlee\\Comprehend\\Directive\\Trivia',
        'Vanderlee\\Comprehend\\Builder\\RuleReference',
        'Vanderlee\\Comprehend\\Extensions\\Optimizer\\Parser\\Sequence',
        'Vanderlee\\Comprehend\\Extensions\\Optimizer\\Parser\\Choice',
        'Vanderlee\\Comprehend\\Extensions\\Optimizer\\Parser\\Repeat',
        'Vanderlee\\Comprehend\\Extensions\\Optimizer\\Parser\\Not',
        'Vanderlee\\Comprehend\\Extensions\\Optimizer\\Parser\\SeparatedList',
        'Vanderlee\\Comprehend\\Extensions\\Optimizer\\Parser\\Trivia',
        'Vanderlee\\Comprehend\\Extensions\\Optimizer\\Parser\\ReadWhileSet',
        'Vanderlee\\Comprehend\\Extensions\\Optimizer\\Parser\\ReadWhileRange',
    ];

    /** @var WeakMap<object, int> Monotonic IDs avoid object-ID reuse without retaining objects. */
    private WeakMap $identities;
    /** @var WeakMap<Parser, bool> False also breaks recursive eligibility walks conservatively. */
    private WeakMap $eligible;
    /** @var WeakMap<ParseSession, Trace> */
    private WeakMap $traces;
    private int $nextIdentity = 0;
    /** @var array<string, true> */
    private array $positions = [];
    /** @var array<string, Observation> */
    private array $policy = [];
    /** @var array<string, Observation> */
    private array $strict = [];
    /** @var array<string, array<string, int>> */
    private array $classes = [];
    /** @var array<string, int> */
    private array $counts = [
        'entries' => 0, 'position_repeats' => 0, 'eligible_entries' => 0,
        'observed_work' => 0,
        'policy_repeats' => 0, 'strict_repeats' => 0,
        'policy_summary_matches' => 0, 'strict_summary_matches' => 0,
        'policy_repeated_work_inclusive' => 0, 'strict_repeated_work_inclusive' => 0,
        'policy_repeated_work_nonoverlap' => 0, 'strict_repeated_work_nonoverlap' => 0,
        'summary_conflicts' => 0, 'aborted_entries' => 0, 'unregistered_or_impure_entries' => 0,
        'policy_summary_conflicts' => 0, 'strict_summary_conflicts' => 0,
        'limited_or_spaced_entries' => 0, 'dropped_positions' => 0,
        'dropped_contexts' => 0, 'oversized_contexts' => 0, 'depth_dropped_entries' => 0,
        'graph_dropped_nodes' => 0,
        'dropped_identities' => 0,
    ];

    public function __construct(
        private readonly int $capacity = 4096,
        private readonly int $maxDepth = 128,
        private readonly int $maxContextBytes = 16384,
    ) {
        if ($capacity < 1 || $maxDepth < 1 || $maxContextBytes < 1) {
            throw new InvalidArgumentException('Profile bounds must be positive');
        }
        $this->identities = new WeakMap();
        $this->eligible = new WeakMap();
        $this->traces = new WeakMap();
    }

    /** @internal Called only after FrozenGrammar has isolated, resolved and checked its graph. */
    public function registerFrozenGraph(Parser $root, int $depth = 0): bool
    {
        if (isset($this->eligible[$root])) {
            return $this->eligible[$root];
        }
        if (count($this->eligible) >= $this->capacity || $depth >= $this->maxDepth) {
            ++$this->counts['graph_dropped_nodes'];
            return false;
        }
        $this->eligible[$root] = false;
        if (!in_array($root::class, self::PURE_CLASSES, true)) {
            return false; // Do not call unknown children()/capability hooks while profiling.
        }
        $pure = !$root->hasExecutableMetadata();
        foreach ($root->children() as $child) {
            $childPure = $this->registerFrozenGraph($child, $depth + 1);
            $pure = $pure && $childPure;
        }
        $this->eligible[$root] = $pure;

        return $pure;
    }

    /** @internal Scalar identities only; no Source, session, tree or execution record is retained. */
    public function identity(object $object): int
    {
        if (isset($this->identities[$object])) {
            return $this->identities[$object];
        }
        if (count($this->identities) >= 3 * $this->capacity) {
            ++$this->counts['dropped_identities'];
            return ++$this->nextIdentity; // Untracked identities can only split observations, never alias them.
        }

        return $this->identities[$object] = ++$this->nextIdentity;
    }

    /** @internal Observe successful checked entry; no recognition or semantic callback is invoked. */
    public function enter(Parser $parser, int $offset, ParseSession $session, bool $retainFailureDiagnostic): void
    {
        ++$this->counts['entries'];
        $trace = $this->traces[$session] ?? ['frames' => [], 'skipped' => 0];
        if ($trace['skipped'] > 0 || count($trace['frames']) >= $this->maxDepth) {
            ++$trace['skipped'];
            ++$this->counts['depth_dropped_entries'];
            $this->traces[$session] = $trace;
            return;
        }
        $parserIdentity = $this->identity($parser);
        $position = $this->identity($session) . ':' . $this->identity($session->source())
            . ':' . $parserIdentity . ':' . $offset;
        if (isset($this->positions[$position])) {
            ++$this->counts['position_repeats'];
        } elseif (count($this->positions) < $this->capacity) {
            $this->positions[$position] = true;
        } else {
            ++$this->counts['dropped_positions'];
        }
        $eligible = $this->eligible[$parser] ?? false;
        if (!$eligible) {
            ++$this->counts['unregistered_or_impure_entries'];
        } elseif ($session->hasRecognitionLimits() || $session->hasActiveSpacing()) {
            $eligible = false;
            ++$this->counts['limited_or_spaced_entries'];
        }
        $policy = null;
        $strict = null;
        if ($eligible) {
            ++$this->counts['eligible_entries'];
            $context = $session->memoizationContext(
                $this,
                $retainFailureDiagnostic,
                intdiv($this->maxContextBytes, 32),
            );
            if ($context === null) {
                $this->counts['oversized_contexts'] += 2;
            } else {
                [$policies, $details] = $context;
                $policy = $this->key([$position, $policies]);
                $ancestry = array_map(
                    static fn(array $frame): array => [$frame['identity'], $frame['offset']],
                    $trace['frames'],
                );
                $strict = $this->key([$position, $policies, $details, $ancestry]);
            }
        }
        $class = $parser::class;
        if (!isset($this->classes[$class]) && count($this->classes) >= $this->capacity) {
            $class = 'other';
        }
        $row = $this->classes[$class] ?? [];
        $row['entries'] = ($row['entries'] ?? 0) + 1;
        $this->classes[$class] = $row;
        $trace['frames'][] = ['class' => $class, 'identity' => $parserIdentity, 'offset' => $offset,
            'policy' => $policy, 'strict' => $strict,
            'start' => $session->workUsed() - 1, 'eligible' => $eligible,
            'policyCovered' => 0, 'strictCovered' => 0];
        $this->traces[$session] = $trace;
    }

    /** @internal A null match means recognition threw; partial records are never reuse candidates. */
    public function leave(ParseSession $session, ?AbstractMatch $match): void
    {
        $trace = $this->traces[$session];
        if ($trace['skipped'] > 0) {
            --$trace['skipped'];
            $this->traces[$session] = $trace;
            return;
        }
        $frame = array_pop($trace['frames']);
        if ($frame === null) {
            return;
        }
        $work = $session->workUsed() - $frame['start'];
        $policyRepeat = false;
        $strictRepeat = false;
        if ($match === null) {
            ++$this->counts['aborted_entries'];
        } elseif ($frame['eligible']) {
            // A bounded outcome summary is a consistency check, not full CST/diagnostic replay proof.
            $summary = [$match->accepted, $match->length, $match->farthestFailure,
                $match->node?->kind, $match->node === null ? 0 : count($match->node->children)];
            $signature = hash('sha256', serialize($summary));
            $policyRepeat = $this->observe($this->policy, $frame['policy'], $signature, 'policy');
            $strictRepeat = $this->observe($this->strict, $frame['strict'], $signature, 'strict');
        }
        foreach (['policy' => $policyRepeat, 'strict' => $strictRepeat] as $kind => $repeat) {
            $covered = $frame[$kind . 'Covered'];
            if ($repeat) {
                ++$this->counts[$kind . '_summary_matches'];
                $this->counts[$kind . '_repeated_work_inclusive'] += $work;
                $this->counts[$kind . '_repeated_work_nonoverlap'] += $work - $covered;
                $covered = $work;
                $row = &$this->classes[$frame['class']];
                $row[$kind . '_summary_matches'] = ($row[$kind . '_summary_matches'] ?? 0) + 1;
                $row[$kind . '_repeated_work_inclusive'] = ($row[$kind . '_repeated_work_inclusive'] ?? 0) + $work;
                unset($row);
            }
            $parent = array_key_last($trace['frames']);
            if ($parent !== null) {
                $trace['frames'][$parent][$kind . 'Covered'] += $covered;
            }
        }
        if ($trace['frames'] === []) {
            $this->counts['observed_work'] += $work;
            unset($this->traces[$session]);
        } else {
            $this->traces[$session] = $trace;
        }
    }

    /** @param array<string, Observation> $observations */
    private function observe(array &$observations, ?string $key, string $signature, string $kind): bool
    {
        if ($key === null) {
            return false;
        }
        if (isset($observations[$key])) {
            ++$this->counts[$kind . '_repeats'];
            if ($observations[$key]['signature'] !== $signature) {
                ++$this->counts['summary_conflicts'];
                ++$this->counts[$kind . '_summary_conflicts'];
                $observations[$key]['signature'] = 'conflict';
                return false;
            }
            ++$observations[$key]['visits'];
            return true;
        }
        if (count($observations) < $this->capacity) {
            $observations[$key] = ['signature' => $signature, 'visits' => 1];
        } else {
            ++$this->counts['dropped_contexts'];
        }

        return false;
    }

    /** @param array<mixed> $context */
    private function key(array $context): ?string
    {
        $remaining = $this->maxContextBytes;
        if (!$this->fits($context, $remaining)) {
            ++$this->counts['oversized_contexts'];
            return null;
        }

        return hash('sha256', serialize($context));
    }

    /** Bound serialization before allocating it; contexts contain scalar arrays only. */
    private function fits(mixed $value, int &$remaining): bool
    {
        $remaining -= is_string($value) ? strlen($value) + 16 : 32;
        if ($remaining < 0) {
            return false;
        }
        if (is_array($value)) {
            foreach ($value as $key => $child) {
                if (!$this->fits($key, $remaining) || !$this->fits($child, $remaining)) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @return array<string, mixed> Counts are bounded observations, never predicted cache hits or CPU savings. */
    public function snapshot(): array
    {
        return $this->counts + ['tracked_positions' => count($this->positions),
            'policy_contexts' => count($this->policy), 'strict_contexts' => count($this->strict),
            'registered_nodes' => count($this->eligible), 'capacity' => $this->capacity,
            'max_depth' => $this->maxDepth, 'max_context_bytes' => $this->maxContextBytes,
            'active_traces' => count($this->traces),
            'classes' => $this->classes];
    }
}
