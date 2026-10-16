# Extraction profiling

The extraction profile separates recognition and CST construction from lazy
application values, cached access, result release and cycle collection. It uses
the existing [Binding comparison](binding-comparison.md) grammar and independent
expected values. The profiler itself requires only the root development
dependencies; installing Binding is unnecessary.

## Reproduce

```sh
php tools/profile-extraction.php --case=nested --size=128 --iterations=15
php tools/profile-extraction.php --case=record --iterations=15
php tools/profile-extraction.php --case=nested --iterations=1 --trace
php tools/profile-extraction.php --case=nested --iterations=1 --trace --without-trees
```

Use `--output=path.json` to save a report. `--source-dir=path/to/src` loads an
alternate source snapshot in a fresh process, allowing the same profiler,
grammar and oracle to measure a baseline. Only accepted comparison cases are
supported. Each worker prepares the frozen grammar and warms it twice before
measurement. Source construction is separate from recognition. Full consumption,
expected domain values, cached identity, raw source reconstruction, CST size and
serialized tree hashes are checked outside the timed phases.

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

`--without-trees` now exercises [tree-free recognition](tree-retention.md).
Reports identify the `trees` setting; disabled runs report zero CST nodes and a
null tree hash. Earlier artifacts below used the default tree-enabled mode.

The [raw artifact](benchmarks/extraction-profile-2026-10-16.json) records PHP
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

The [span reuse artifact](benchmarks/span-reuse-2026-10-16.json) uses the same
profiler, with five fresh workers per variant/case and 15 observations per worker,
alternating execution order. Nested recognition creates 1,929 spans instead of
2,207 (278 fewer, 12.6%). Retained memory after projection falls from 600,792 to
574,392 bytes (4.4%); record memory falls from 71,912 to 69,416 bytes (3.5%).
Tree hashes, parser-entry metrics and other source allocation counts agree.

Median-of-worker-median recognition time was 11.976 ms before and 12.472 ms after
for nested input; record time was 1.326 ms before and 1.315 ms after. These local
measurements do not demonstrate a speed improvement. The change is retained for
its lower allocation count and retained memory, with no claim that it closes
the extraction throughput gap with Binding. Regression cases cover shared and
different multibyte intervals, both single-child authoring forms, independent
cached values and deferred, idempotent effects.

## Avoiding state for stateless preceding matches

`Success::addPrecedingMatch()` now skips a recognized child without execution
state. Such a child has no deferred operations to run before the following
match. Its concrete syntax remains in the enclosing parser's CST children.
Previously, retaining it created state on the following match and could keep an
otherwise unnecessary execution graph alive. Children with deferred operations
still retain their original ordering and commit lifecycle.

The [state allocation artifact](benchmarks/state-allocation-2026-10-16.json)
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
