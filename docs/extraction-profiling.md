# Extraction profiling

> Historical benchmark artifacts referenced on this page are no longer included in the documentation.

The extraction profile separates recognition and CST construction from lazy
application values, cached access, result release and cycle collection. It uses
the existing [Extraction comparison](extraction-comparison.md) grammar and independent
expected values. The profiler itself requires only the root development
dependencies; installing Extraction is unnecessary.

## Reproduce

```sh
php tools/profile-extraction.php --case=nested --size=128 --iterations=15
php tools/profile-extraction.php --case=record --iterations=15
php tools/profile-extraction.php --case=nested --iterations=1 --trace
```

Use `--output=path.json` to save a report. `--source-dir=path/to/src` loads an
alternate source snapshot in a fresh process, allowing the same profiler,
grammar and oracle to measure a baseline. Only accepted comparison cases are
supported. Each worker prepares the frozen grammar and warms it twice before
measurement. Source construction is separate from recognition. Full consumption,
expected domain values, cached identity, raw source reconstruction, CST size and
serialized tree hashes are checked outside the timed phases.

`--scan-spacing` selects `readWhileSet()` whitespace, matching the faster
comparison variant. Without it, the original `set()->many()` policy is used.
`--scan-tokens` independently selects the composed set/range scanner token grammar
instead of regex tokens. For example:

```sh
php -d pcov.enabled=0 tools/profile-extraction.php --case=nested --size=128 --iterations=31 --scan-spacing --scan-tokens
```

Compare the same token grammar across revisions: scanner and regex variants have
different CST shapes. Parser-entry metrics in this tool use an unfrozen graph;
they do not count the prepared sequence's dispatch reduction. Core-only traces
also omit allocations and methods inside extension implementations.

`--trace --failure-details` adds grouped recognition allocation origins for
Failure and Diagnostic: method call stacks, diagnostic byte offsets, expectations
and rule paths, and failure progress. It stores copied scalar/array details, not
the execution records. This adds probe overhead and is for attribution only.
See the failure-path investigation.

Parser-entry metrics run separately so their retained position map does not
distort phase memory. Reports include PHP/PCRE versions and source/tool hashes.
Memory deltas describe retained PHP usage above a warmed baseline, including the
source and result; they are neither peak memory nor cumulative allocated bytes.
Input fixtures and prepared grammars are already retained at that baseline.

`--trace` uses the existing development dependency `nikic/php-parser` to wrap
method bodies and `new` expressions in **copies** under ignored
`build/profile-instrumented-PID/`. Production source and dependencies are unchanged.
Recognition and first-value traces are separate. Generator methods are excluded
from method timing. Counts cover source methods and their `new` expressions,
including discarded speculative objects; they exclude PHP internal allocations
and constructors outside that source tree. Probe overhead distorts traced times
and memory, so use untraced workers for performance conclusions. Instrumented
self-times help locate candidates but do not establish native CPU percentages.

## Local result, 2026-10-16

The raw artifact records PHP
8.2.10 on Windows, five fresh workers per variant and case, with 15 observations
per worker. Execution alternated baseline/current order. Values below are the
median of the five worker medians, not confidence intervals.

| Phase | Nested before | Nested after | Record before | Record after |
| --- | ---: | ---: | ---: | ---: |
| Source construction | 5.3 µs | 5.8 µs | 1.2 µs | 1.4 µs |
| Recognition and CST | 12.887 ms | 12.504 ms | 1.161 ms | 1.233 ms |
| First value | 195.9 µs | 193.5 µs | 23.6 µs | 25.2 µs |
| Cached value | 1.1 µs | 1.1 µs | 1.0 µs | 1.0 µs |
| Result release | 83.6 µs | 88.5 µs | 8.4 µs | 9.5 µs |
| Cycle collection | 13.6 µs | 13.3 µs | 3.6 µs | 4.8 µs |

The nested case retains 822 CST nodes and uses 596,952 bytes after recognition,
600,792 after value projection. The record retains 69 nodes and uses 70,440 and
71,912 bytes respectively. These counts, deltas and tree hashes were unchanged
by the optimization. Recognition metrics were also unchanged: the nested case
has 1,104 parser entries and eight repeated parser/position pairs.

The nested recognition trace creates 2,207 spans, 828 successes, 828 concrete
nodes, 550 execution states, 276 failures, 276 diagnostics, 272 trivia directives,
143 semantic projections, one session and one result. First-value projection
creates another 143 result wrappers, one execution state and one semantic
projection. These are source allocation counts, not retained-object counts;
application object construction is outside the instrumented source tree.

## Applied optimization and next candidate

Repeated UTF-8 boundary checks previously traversed several forwarding and
validation methods. `Utf8Source::isUnitBoundary()` now checks the immutable buffer
bounds and continuation byte directly; the code-point API delegates to it.
Both APIs preserve byte offsets, interior-byte rejection, EOF and out-of-range
exceptions, covered by multibyte and extreme-offset regression cases.

This removes 7,966 net traced method calls from nested recognition (69,909 to
61,943), with unchanged allocation counts. A separate 440,000-call boundary
probe took 114.43 to 103.45 ms in the nested workers and 112.78 to 100.76 ms in
the record workers: approximately 10–11% faster in isolation. The nested parse
improved in this run while the record slowed; exploratory runs varied as well.
No consistent end-to-end parsing speedup or memory reduction is established.

Recognition/CST work remains much larger than domain projection. The next
candidate is reducing intermediate span and execution-state allocation while
preserving immutable trees, diagnostics, deferred values and explicit effects.
That requires its own allocation/lifetime analysis and equivalence tests.
The small repeated-position count does not justify enabling memoization.

## Span reuse experiment

`Parser::success()` now reuses a single child's immutable span when source,
start and end all match the parent interval. Parent and child still have separate
execution records, concrete nodes, lazy values and effect lifecycles. Different
intervals receive a new span; there is no pool or retained lookup table.

The span reuse artifact uses the same
profiler, with five fresh workers per variant/case and 15 observations per worker,
alternating execution order. Nested recognition creates 1,929 spans instead of
2,207 (278 fewer, 12.6%). Retained memory after projection falls from 600,792 to
574,392 bytes (4.4%); record memory falls from 71,912 to 69,416 bytes (3.5%).
Tree hashes, parser-entry metrics and other source allocation counts agree.

Median-of-worker-median recognition time was 11.976 ms before and 12.472 ms after
for nested input; record time was 1.326 ms before and 1.315 ms after. These local
measurements do not demonstrate a speed improvement. The change is retained for
its lower allocation count and retained memory, with no claim that it closes
the extraction throughput gap with Extraction. Regression cases cover shared and
different multibyte intervals, both single-child authoring forms, independent
cached values and deferred, idempotent effects.

## Avoiding state for stateless preceding matches

`Success::addPrecedingMatch()` now skips a recognized child without execution
state. Such a child has no deferred operations to run before the following
match. Its concrete syntax remains in the enclosing parser's CST children.
Previously, retaining it created state on the following match and could keep an
otherwise unnecessary execution graph alive. Children with deferred operations
still retain their original ordering and commit lifecycle.

The state allocation artifact
compares this change against span reuse, using five fresh workers per
variant/case, 15 observations per worker and alternating order. Nested recognition
creates 417 execution states instead of 550 (24.2% fewer). Other source allocation
counts, parser-entry metrics and serialized CST hashes are unchanged.

| Measurement | Nested before | Nested after | Record before | Record after |
| --- | ---: | ---: | ---: | ---: |
| Recognition/CST median | 12.602 ms | 12.314 ms | 1.234 ms | 1.187 ms |
| First value median | 189.3 us | 189.5 us | 25.4 us | 25.0 us |
| Retained bytes after value | 574,392 | 398,144 | 69,416 | 56,848 |

Times are medians of worker medians in this local environment. Recognition was
2.3% faster for nested input and 3.8% for the record in this run; these small
timing differences do not establish a universal speedup. Retained memory fell
30.7% and 18.1% respectively. Tests cover releasing stateless preceding matches,
retaining effectful ones, empty and nonempty selected trivia, abandoned extension
trivia, deferred execution and repeated commits. This does not remove execution
state required by values, validation, recovery or effects.

## Direct syntax from proven literal batches

`Optimizer\LiteralSequence::attempt()` now returns fresh immutable ConcreteNode
operands after its compiled terminal succeeds. It no longer wraps each operand in
a temporary Success execution record. The optimized Sequence keeps these nodes in
concrete order alongside scanner and ordinary matches, retaining the latter's
semantic, validation, collection and commit dependencies. A full-width first
literal node can still share its span with the parent, including when subsequent
literal operands are empty. The compiled terminal and enclosing sequence retain
their own execution records and use the same recognition runtime.

Eligibility, preparation recipes, failed-run fallback, scopes and finite-budget
paths are unchanged. Scanner batches still create their existing operand records.
No CST nodes are removed, and nothing evaluates values or commits effects during
recognition. The change reduces temporary objects; it does not promise smaller
retained trees or better asymptotic complexity.

Use the focused harness with the two pre-change classes saved as
`LiteralSequence.php` and `Sequence.php` in a baseline directory. The baseline for
this run is `c8b47ae:extensions/Optimizer/LiteralSequence.php` and
`c8b47ae:extensions/Optimizer/Parser/Sequence.php`, retained locally under ignored
`build/literal-batch-records/before/`:

```sh
php -d pcov.enabled=0 tools/benchmark-literal-batches.php --baseline=build/literal-batch-records/before --case=flat --workers=3 --iterations=31
php -d pcov.enabled=0 tools/benchmark-literal-batches.php --baseline=build/literal-batch-records/before --case=single --workers=3 --iterations=31
php -d pcov.enabled=0 tools/benchmark-literal-batches.php --baseline=build/literal-batch-records/before --case=mixed --workers=3 --iterations=31
php -d pcov.enabled=0 tools/benchmark-literal-batches.php --baseline=build/literal-batch-records/before --case=flat --workers=1 --iterations=3 --trace
```

Without `--baseline`, only the current implementation runs. Each worker freezes
and warms the grammar outside recognition timers. Every observation checks full
consumption at a nonzero byte offset, an independently expected semantic value,
the complete serialized CST hash and diagnostics against ordinary freezing,
repeated value reads, and deferred/idempotent commit. Source construction, value
access, serialization, correctness checks and release are outside recognition
timers. Preparation is reported separately. `--trace` instruments a copy of the
Success class; constructor counts include calls originating in extensions, but
traced times and memory are distorted and must not be used for speed conclusions.

Windows/PHP 8.2.10/PCRE 10.40, PCOV disabled, three fresh workers per variant with
alternating before/after order, three warmups and 31 observations. No test suite
overlapped timing; host load was uncontrolled. Recognition/CST values are medians
of worker medians; memory is incremental used PHP bytes in an untraced worker,
not RSS or cumulative allocation volume:

| Fixture | Recognition before / after | Peak bytes before / after | Success constructors before / after |
|---|---:|---:|---:|
| 128 flat Text operands | 207.6 / 153.5 us | 69,816 / 47,216 | 130 / 2 |
| One operand containing 64 repeated pairs | 79.8 / 79.3 us | 24,488 / 24,488 | 3 / 2 |
| Mixed literal, scanner and executable operands | 83.8 / 81.5 us | 11,440 / 11,000 | 11 / 7 |

Prepared/retained-result memory was unchanged: 12,552/40,664 bytes for flat,
16,904/21,360 for single and 8,224/7,304 for mixed. Preparation medians were
291.8/307.3, 549.0/531.2 and 156.7/162.0 us respectively. Preparation still uses
ordinary recognition to classify source-free recipes; no preparation improvement
is claimed. Raw reports, saved classes and control bootstraps are locally retained
under ignored `build/literal-batch-records/`.

The flat fixture measured about 26% less recognition time and 32% lower peak used
PHP memory in this run. Single/mixed timing differences are small, and these
synthetic results do not establish an application-throughput improvement.
Three alternating workers with 15 observations on the existing extraction profiler
measured nested recognition at 4.0550/3.9331 ms and record at 0.6619/0.6526 ms;
retained memory and tree hashes were unchanged. The nested constructor trace was
164 before and after: warmed extraction paths did not exercise this removal, so
those control timing differences do not demonstrate a benefit from this change.

Focused regressions cover direct operand syntax, metadata, source ownership,
inherited diagnostics, limited fallback, mixed zero-width/scanner/literal ordering,
discarded attempts, cached values and collections, validation, explicit effect
order and span reuse. Existing literal/scanner suites compare byte/UTF-8 memory and
file trees, failures and exhausted limits. Reject this change if any of those
contracts diverges or mixed-child bookkeeping outweighs the targeted allocation
reduction on representative workloads.
