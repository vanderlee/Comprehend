# Performance measurements and retention guarantees

> Historical benchmark artifacts referenced on this page are no longer included in the documentation.

For application-value extraction, see the [phase and allocation profile](extraction-profiling.md).
It separates recognition/CST costs from first and cached value projection.

Phase 4 records evidence rather than promising a universal performance win.
The measurements below were collected locally on Windows, PHP 8.2.10 and PCRE
10.40, with three iterations per case in fresh worker processes. JSON artifacts
include runtime, input bytes, source kind, output mode, min/median/max time,
source-setup time, allocated PHP process peak and reproduction commands.

## Reproduce

```sh
php tools/benchmark.php --iterations=3 --size=2048
php tools/benchmark.php --source=memory-byte --iterations=3 --size=2048
php tools/benchmark.php --source=file-byte --iterations=3 --size=2048
php tools/benchmark.php --source=file-utf8 --iterations=3 --size=2048
php tools/benchmark.php --source=file-utf8 --iterations=3 --size=65536 --case=regex
php tools/benchmark.php --source=file-utf8 --iterations=3 --size=65536 --case=regex-window
php tools/benchmark.php --source=file-utf8 --iterations=3 --size=1048576 --case=regex-window --mode=recognition
php tools/benchmark.php --iterations=3 --size=2048 --case=shared-prefix --mode=recognition --profile
```

Add `--output=path.json` to save a report. `--case` and `--mode` select focused
measurements. Cases include text, Unicode, RFC URI, consuming parenthesized
recursion, shared-prefix alternatives, malformed input, chunked large text and
regex. Source setup is timed separately; file setup includes creation of the
fixture, disk snapshot copying and UTF-8 validation. The generated input string
is retained in the worker, so process peaks include fixture storage too.

Modes are recognition (which **always includes CST construction**), CST array
serialization, named AST projection and a cached semantic mapping to byte length.
They measure a complete parse plus that requested output, not isolated output
costs. Failed cases do not project a tree/value. The harness verifies acceptance
before accepting a measurement. Small cases and three samples are noisy; these
results are engineering baselines, not platform-independent guarantees. Byte
and UTF-8 source results should only be compared under the same encoding mode.

## Recorded comparisons

### Direct facade snapshots (2026-10-23)

`FrozenGrammar::from($facade, $flags)` now copies the facade's owned parser directly
into the resolving destination snapshot. Previously it first created a detached
authoring export and then copied that export again. `Comprehend::parser()` keeps
its normal detached, non-resolving behavior; an optional internal destination
context lets the freeze factory avoid the intermediate graph. The factory still
validates flags, resolves the optimizer when requested, isolates the graph, checks
safety and prepares recognition. It creates fresh snapshots without consulting
facade caches or dispatching a custom root `freeze()` override, as before.

This removes one O(nodes + edges) authoring-copy pass over the exported graph,
including its temporary graph storage and snapshot indexes. It does not eliminate
the final detached snapshot or cache mutable definitions. Factory-returned facades
still export separately: sharing their internal roots across named productions
could merge diagnostic metadata or parser identities. Ordinary facade `freeze()`
already avoided this intermediate export and is unchanged.

Three fresh Windows/PHP 8.2.10 workers per variant alternated before/after order,
with PCOV disabled, one warmup and 31 observations per fixture/flag pair. Timers
cover complete `FrozenGrammar::from($facade, $flags)` preparation, excluding facade
construction, source setup, correctness checks, recognition and release. Medians
of worker medians, in microseconds; memory is incremental used PHP bytes from a
separate warmed preparation, not RSS:

| Fixture / flags | Preparation before / after | Peak bytes before / after |
|---|---:|---:|
| Text / NONE | 5.2 / 3.7 | 4,584 / 4,136 |
| Text / OPTIMIZE | 6.8 / 5.5 | 4,584 / 4,136 |
| 512 flat Text operands / NONE | 1,323.9 / 948.1 | 834,136 / 592,032 |
| 512 flat Text operands / OPTIMIZE | 5,036.0 / 4,525.1 | 1,033,432 / 791,288 |
| 128 nested Sequences / NONE | 558.2 / 397.5 | 306,080 / 228,832 |
| 128 nested Sequences / OPTIMIZE | 6,483.1 / 6,158.2 | 489,664 / 407,296 |
| 128 references / NONE | 722.1 / 645.8 | 203,840 / 151,688 |
| 128 references / OPTIMIZE | 2,599.4 / 2,454.1 | 391,624 / 339,432 |
| RFC URI / NONE | 1,015.0 / 989.7 | 293,368 / 292,984 |
| RFC URI / OPTIMIZE | 2,315.2 / 2,237.9 | 293,424 / 293,040 |

The reference fixture uses one Text production 128 times. The URI facade's
unresolved export is small, so the removed pass saves little compared with
resolution and preparation. The flat graph measured about 28% faster preparation
without optimization and 10% with it, with about 29% and 23% lower peaks. Retained
graph memory was effectively unchanged in most fixtures; a nested optimized
fixture retained 328,264 bytes before and 323,144 after. No general retained-memory,
cold-process or recognition-throughput improvement is claimed. Host activity was
uncontrolled, and class loading was warmed before observations.

Baseline source, worker and raw reports are under ignored `build/direct-snapshot/`.
While retained, reproduce in fresh alternating workers:

```sh
php -d pcov.enabled=0 -d memory_limit=512M build/direct-snapshot/measure.php before
php -d pcov.enabled=0 -d memory_limit=512M build/direct-snapshot/measure.php after
```

Workers compare valid, truncated and trailing-input acceptance, nonzero-offset
CSTs, diagnostics and values against ordinary exported-parser freezing. New
`FrozenGrammarTest` cases verify one resolving copy per shared participant, public
export isolation, custom freeze dispatch, fresh factory resolution, old snapshots
after redefinition, failed-copy retries, byte spans, cached values and explicit
effects under both flag sets. Reject the optimization if ownership, freshness,
custom dispatch, diagnostics or execution lifecycle differs.

### Compact analysis ancestry (2026-10-23)

`GraphAnalysis` stores one first-discovery parent ID and the original collection
label per node instead of a full path array for each node. It reconstructs paths
only for warnings and errors. Leading-cycle detection also uses one active map,
restored in `finally`, instead of copying the ancestry map at each recursive call.
Warning order, exact error text, first-discovery paths through shared graphs and
custom analysis call order remain unchanged. Nullability scheduling is unchanged.

Three fresh Windows/PHP 8.2.10 workers per variant used alternating before/after
order, PCOV disabled, one warmup and 21 timed validations per fixture. Graph
construction, resolution, correctness checks and object release are outside the
analysis timer. Medians of worker medians, in microseconds:

| Graph | Analysis before / after | Retained analysis bytes before / after | Incremental peak bytes before / after |
|---|---:|---:|---:|
| 64 consuming wrappers | 140.0 / 116.3 | 86,080 / 33,104 | 224,488 / 60,464 |
| 256 consuming wrappers | 771.2 / 468.9 | 1,355,840 / 82,256 | 3,665,640 / 191,376 |
| 1,024 consuming wrappers | 15,285.1 / 1,884.9 | 15,239,232 / 328,016 | 44,324,584 / 934,424 |
| 512 nullable wrappers | 3,061.7 / 1,455.2 | 4,585,536 / 164,176 | 12,236,520 / 405,784 |
| 128 branches sharing 256 wrappers | 1,176.1 / 782.1 | 1,407,960 / 82,256 | 3,747,136 / 259,144 |
| 512 flat Text operands | 458.6 / 445.1 | 233,952 / 164,176 | 295,936 / 226,192 |
| Resolved RFC URI | 521.7 / 480.5 | 153,368 / 49,488 | 228,480 / 124,600 |

Consuming chains end in one Text; nullable chains end in Nothing. Memory uses a
separate warmed validation and keeps the analysis object alive. These are PHP
used bytes above the constructed-graph baseline, not RSS or frozen-grammar
retention. The 1,024-wrapper case measured about 8.1x faster analysis with about
98% less retained analysis memory and incremental peak memory. Host activity was
uncontrolled. These measurements do not establish a complete-freeze or recognition
speedup; optimizer preparation and CST construction are separate costs.

Ancestry storage is O(nodes), with an O(depth) active map. Reconstructing a
diagnostic costs O(path length). Many deep opaque warnings still require output
proportional to their full paths. Recursive PHP call stacks, nullability dependency
tables and optimizer plans retain their separate costs and bounds.

The baseline class, worker and raw reports are under ignored
`build/analysis-ancestry/`. While retained, reproduce in fresh alternating workers:

```sh
php -d pcov.enabled=0 -d memory_limit=512M build/analysis-ancestry/measure.php before
php -d pcov.enabled=0 -d memory_limit=512M build/analysis-ancestry/measure.php after
```

The focused GraphAnalysis and AnalysisContract tests pass against both the saved
baseline and current class. New checks pin exact shared-graph warning paths and
order, captured labels, complete structural/progress error ancestry, cycle paths
after completed siblings, and retries after failed analysis. Reject the change if
diagnostic ordering/text, custom analysis behavior or graph safety checks diverge,
or representative overhead outweighs the deep-graph memory improvement.

### Shared optimizer preparation (2026-10-23)

Optimizer preparation now shares successful literal and lexical-lookahead
classifications within one resolved, safety-checked graph. Descriptors include
structural height, so reuse under a deeper caller still obeys the original
classification depth limits. Failed classifications are not cached. Terminal
classification remains direct; bounded lexical tables remain independently owned
by their combinators.

Literal compilation also reuses source-free, operand-relative syntax templates
when the same parser occurs on multiple graph edges. Recognition still constructs
fresh CST nodes and execution records for every occurrence. A temporary weak-keyed
context owns the descriptors and template index; it is released after preparation,
including when preparation throws. There is no cross-freeze definition cache or
shared recognition session, semantic projection or effect execution.

Three fresh Windows/PHP 8.2.10 workers per variant alternated before/after order,
with PCOV disabled, one warmup and 31 observations per fixture. Preparation includes
snapshot copying, resolution, safety analysis and optimization; source construction,
correctness comparisons and release are outside preparation timers. Recognition
includes the CST. Medians of worker medians, in microseconds:

| Fixture | Preparation before / after | Reused recognition before / after |
|---|---:|---:|
| One Text | 4.9 / 5.4 | 5.7 / 5.5 |
| 128 distinct flat Text operands | 1066.9 / 1120.3 | 193.4 / 196.2 |
| 128 nested Sequences | 6670.4 / 6396.6 | 475.5 / 477.3 |
| 128 occurrences of one literal subgraph | 5378.7 / 329.6 | 1359.2 / 1349.1 |
| RFC URI | 2671.3 / 2308.2 | 852.8 / 840.7 |

The shared subgraph is `Sequence(Text('ab'), Repeat::exact(Text('c'), 8))`.
Its retained prepared graph fell from 343,352 to 20,264 used PHP bytes, with
incremental preparation peaks falling from 354,376 to 39,752 bytes. The flat
control retained the same 97,896 bytes but its peak rose from 183,240 to 206,616
bytes. Other retained graph measurements were unchanged. These are PHP used bytes,
not RSS. The flat control also shows approximately five percent preparation
overhead; this is a tradeoff for overlapping graphs, not a universal cold-parse win.
Host activity was uncontrolled, and no general recognition speedup is established.

Successful descriptor classification can reuse a shared subtree rather than
traverse it for each parent. Graph occurrence counting and temporary maps add
O(nodes + edges) traversal work and O(nodes) indexing space. Descriptor strings
and expanded syntax remain subject to their existing byte/node/depth bounds.
Template sharing reduces duplicate retained recipes, but each input occurrence
still requires its full CST. Deep unshared templates can still dominate preparation.

The local baseline sources, worker and raw reports are retained under ignored
`build/preparation-reuse/`. While retained, run these in fresh alternating workers:

```sh
php -d pcov.enabled=0 -d memory_limit=512M build/preparation-reuse/measure.php before
php -d pcov.enabled=0 -d memory_limit=512M build/preparation-reuse/measure.php after
```

Workers compare valid, truncated and trailing-input acceptance, byte-offset CSTs,
diagnostics and values with ordinary freezing. `PreparationTest` adds depth-bound,
expanded-size, cycle and source/session-release checks. `PreparationReuseTest`
compares cold/warm trees, named ASTs, diagnostics and every tested exhausted
work/depth budget on byte and UTF-8 sources at a nonzero offset. Existing lifecycle
tests cover definition refresh, pure recognition, cached values and explicit effects.
Reject this reuse if any of those contracts diverge, sources or execution state are
retained, or representative preparation overhead outweighs the overlapping-graph gains.

### Choice dispatch and prefix-plan comparison (2026-10-23)

`Optimizer\ChoiceDispatch` provides conservative first-byte rejection for exact
built-in ASCII Text alternatives outside existing bounded lexical tables. Plans
require at least two distinct eligible first bytes and leave the first alternative
on its ordinary path. Unknown, nullable and executable operands are never inferred
through. Rejected operands still merge their original diagnostics in alternative
order and charge their original entry/mismatch work. Matching candidates retain
checked recognition. Finite budgets, active spacing, case-insensitive scopes and
custom choice subclasses retain their prior paths. This is not general first-set
analysis or a way to remove the ordered alternative loop.

The maintained `tools/compare-choice-plans.php` compares the previous optimized
Choice, production dispatch, and two research-only plans: a byte trie and a
compressed prefix tree (internal prefix factoring). Both prototypes classify flat
literal alternatives, replay failure observations in original order through the
ordinary Choice selector, and recognize successful operands normally. They retain
the original CST; rewriting `P a | P b` as a new authored `P (a | b)` would change
tree boundaries and can change guards, scopes and ranked selection. That grammar
rewrite is not an equivalent benchmark or a production optimization here.

Fresh PHP 8.2.10/Windows workers used PCOV disabled, three warmups and 31 timed
recognition/CST observations. Three workers per variant alternated forward/reverse
variant order. Timings exclude source setup, correctness checks, result release
and preparation. The test suite ran afterward. Host activity was uncontrolled.
The fixture has 128 alternatives, a 512-byte prefix plus three decimal suffix
bytes; `distinct` prepends one of 90 initial bytes. These are medians of worker
medians in milliseconds, not application throughput guarantees:

| FIRST-policy fixture | Previous lexical | Dispatch | Byte trie | Factored prefix |
|---|---:|---:|---:|---:|
| Diverse initial bytes, last alternative | 0.641 | 0.325 | 0.459 | 0.270 |
| Shared prefix, last alternative | 0.836 | 0.845 | 0.540 | 0.387 |
| Shared prefix, late malformed input | 0.863 | 0.842 | 0.531 | 0.355 |
| First alternative succeeds | 0.0110 | 0.0109 | 0.1990 | 0.0314 |
| Eight-byte prefix, warm lexical tables | 0.488 | 0.473 | 0.285 | 0.282 |
| Same short literals, saturated tables | 0.893 | 0.904 | 0.282 | 0.284 |

Diverse-input worker medians ranged 0.624–0.646 ms before and 0.322–0.329 ms
with dispatch. Checked parser entries fell from 129 to 4; work remained 1,285
units. Shared-prefix entries/work remained 129/65,829. Its small apparent dispatch
slowdown is an overhead to watch, not evidence that first-byte filtering helps
shared prefixes. The LONGEST shared-prefix control measured 0.841/0.862/0.560/0.385
ms respectively. Short warmed tables already reduce checked entries to one;
prototypes instead charge ordinary Text work, so their unlimited counters differ.
The saturated fixture fills each table with 160 distinct failing lookaheads before
the measured input. Production dispatch does not replace these tables or their
capacity/saturation behavior.

For diverse literals, preparation retained 64,568 used bytes before and 160,656
with dispatch, including static expectations. A dispatch-disabled Choice gains
one empty array property; the shared fixture retained the same measured 64,568
bytes in this run. The byte trie retained 28,657,784 bytes across 46,545 nodes;
factoring retained 184,280 bytes across 167 nodes. Shared-prefix plans retained
455,592/141,432 bytes across 656/145 nodes respectively. These are PHP used bytes,
not RSS or allocator peaks. The report also records preparation and parse peaks,
first-parse time, and retained result memory. All four diverse-input variants
retained the same 2,592-byte selected result in this probe. Preparation timings for
production include freezing, while prototypes omit snapshot/safety analysis;
their absolute preparation times must not be treated as equivalent cold parsing.
Diverse-fixture preparation medians were 0.618 ms before and 0.805 ms with
dispatch, versus 38.907 ms for trie construction and 0.342 ms for factored-plan
construction. First-parse results and reuse counts are needed to assess amortization
for an application; warm recognition alone does not establish a cold-parse gain.

Dispatch adds preparation proportional to inspected literal bytes and retains
source-free failure recipes. Recognition still visits O(alternatives) entries to
preserve ordered diagnostics; it replaces full-literal attempts with one-byte
probes where rejection is proven. Prototypes classify the matched prefix plus
candidate outcomes, but still replay O(alternatives) failure observations. Trie
storage grows with distinct byte prefixes; factoring compresses unary paths into
string comparisons. Their eager whole-choice classification explains the severe
early-success regressions. Neither prototype is enabled in production. Factoring
is the better further experiment for long late-failing chains, but requires lazy
classification and broader eligibility/lifecycle proofs before promotion.

Each worker performs 216 cold/warm differential checks against ordinary freezing:
all policies, byte/UTF-8 sources, nonzero offsets, EOF, malformed input, prefix
overlap and ties, prefix/all-input entry points, diagnostics, CST/AST and values.
The new unit/feature regressions additionally check guards, cached collections,
explicit effects, string keys, file pages, inherited scopes, authoring copies,
partial failure progress and exact exhausted budgets. No memoization is added;
none of this demonstrates context-equivalent repeated recognition for a cache.

Reproduce the current comparison (the lexical control disables dispatch through
reflection after preparing the current graph):

```sh
php -d pcov.enabled=0 tools/compare-choice-plans.php --case=distinct --workers=3
php -d pcov.enabled=0 tools/compare-choice-plans.php --case=shared --workers=3
php -d pcov.enabled=0 tools/compare-choice-plans.php --case=first --workers=3
php -d pcov.enabled=0 tools/compare-choice-plans.php --case=shared --policy=longest --workers=3
```

Other cases are `short`, `saturated`, `malformed`, `prefixes` and `empty`. To
reproduce the original implementation control used above, save
`b30235b:extensions/Optimizer/Parser/Choice.php` as
`build/choice-dispatch/original-choice.php` and add
`--baseline-choice=build/choice-dispatch/original-choice.php`; the control worker
loads that class before discovery. Raw observations are retained under ignored
`build/choice-dispatch/` while available. Reject dispatch if diagnostic ordering,
selection, work or lifecycle comparisons diverge, or representative regressions
outweigh the targeted benefit. Reject either eager prefix plan as a general
replacement based on the measured early-success and preparation-memory costs.

### Flat compiled-literal failures (2026-10-23)

When a compiled literal run contains only exact built-in ASCII Text operands,
its mismatch progress identifies the failing operand. Recognition now skips
successful preceding operands and invokes the original failing Text for its
expectation, rule path and partial progress. Their parser-entry and literal-unit
work charges remain, including the existing speculative compiled comparison.
Finite work/depth limits, active spacing and case-insensitive scopes still use
ordinary recognition. Nested runs retain their existing failure fallback.

The 128-alternative research fixture shares a 2,048-byte Text prefix, followed by
a distinct three-byte Text suffix, and accepts the final alternative. Warmed
optimized parser entries fell from 511 to 384, while work remained 523,076 units.
The selected CST matched the ordinary graph. Three fresh workers per variant
used alternating before/after order, three warmups and 31 observations, with
PCOV disabled on Windows/PHP 8.2.10. These initial timing workers overlapped
test-suite execution. A follow-up after the suite completed used the same protocol:
long-prefix worker medians ranged 10.657–10.875 ms before and 7.501–9.443 ms after;
medians of worker medians were 10.814 and 7.635 ms. Host activity remained
uncontrolled; these synthetic observations do not establish application throughput
or a universal speedup. Short-prefix lexical-table and distinct-literal controls
retained one checked parser entry in both versions.

Preparation retains an additional list of Text references for each eligible run;
other runs retain null. Eligibility is determined during existing classification.
There is no source/result cache. Failure recovery walks operand boundaries and
still scans the failing operand, but avoids rescanning and building discarded
successes for preceding operands. Successful recognition and retained CST
complexity are unchanged. No retained-memory improvement is established.

The ignored research driver and reports are under `build/choice-research/`.
While retained, reproduce the current probe with:

```sh
php -d pcov.enabled=0 build/choice-research/probe.php
php -d pcov.enabled=0 -d auto_prepend_file=build/choice-research/original-literal.php build/choice-research/probe.php
```

`CompiledLiteralFailureTest` checks all choice policies, cold/warm observations,
ordered diagnostics, byte/UTF-8 sources, nonzero offsets, operand boundaries,
EOF, file pages, exact failure progress, preserved work and exhausted limits.
Existing mixed-run tests check preceding guards, lazy values and explicit effects.
Reject this specialization if diagnostic ordering, scope restoration, execution
limits or lifecycle behavior differs, or isolated representative workloads show
regressions that outweigh the removed successful-prefix recognition.

### Separated-list staging buffers (2026-10-22)

`SeparatedList::attempt()` appends selected matches and concrete syntax only
after its operand succeeds. A failed item therefore leaves the staged separator
and its preceding trivia unchanged. The list now retains that existing pair
buffer for optional trailing acceptance, removing the separate separator-array
snapshots that forced copy-on-write when a successful item was appended. At a
finite item bound, the final separator attempt appends directly to the selected
and concrete arrays; failure leaves those arrays unchanged. Pair staging remains
in place where a separator may need to roll back with its following item.

A source counter on the saved original 128-ID nested scanner-spacing fixture
recorded 266 removed snapshot assignments. Successful item appends followed
nonempty shared prefixes containing 258 array slots in total. These count source
operations and logical array slots, not PHP heap allocations or allocated bytes.
The change introduces no new preparation, cache or persistent state. Retained
tree complexity and parsing complexity are unchanged.

Fresh Windows/PHP 8.2.10 timing workers disabled PCOV, warmed three times and
measured 31 recognition/CST observations each, alternating original/current
order. Preparation, source setup, correctness, values, serialization, release and
collection were outside timing. Five workers per variant initially measured
scanner-spacing medians of 5.398 versus 5.387 ms and repeated-character medians
of 6.046 versus 6.756 ms. A seven-worker follow-up reversed the apparent
repeated-character slowdown: medians were 8.353 versus 7.984 ms, with scanner
spacing at 6.623 versus 6.001 ms. Worker ranges overlap substantially; uncontrolled
host variation establishes no consistent throughput improvement or regression.

Retained recognition memory and incremental parse peaks were unchanged:
388,600 / 400,512 used bytes for scanner spacing and 389,608 / 403,320 for
repeated-character spacing. CST hashes, parser entries and successful work
counts agreed. Removing short-lived array copies does not establish lower
retained memory or a different asymptotic bound.

A 52,992-case original/current comparison matched acceptance, byte consumption,
ordered diagnostics, CST/AST, cached values, effects and execution-limit outcomes.
New regressions check bounded and unbounded trailing acceptance, partial separator
failure, discarded item spacing, zero-width trivia, separator collections and
exact commit ordering in both freeze modes. Original sources, counters, workers
and raw reports are under ignored `build/list-staging/`. While retained, reproduce:

```sh
php -d pcov.enabled=0 build/list-staging/count.php
php -d pcov.enabled=0 build/list-staging/before.php --spacing=scan
php -d pcov.enabled=0 build/spacing-research/experiment.php --spacing=scan
php -d pcov.enabled=0 build/list-staging/differential.php --original-list
php -d pcov.enabled=0 build/list-staging/differential.php
```

Use fresh alternating workers and `--spacing=set` for the repeated-character
control. Reject the optimization if staged data leaks after failure, separator
permissions or rollback differ, effects reorder, limits change, or stable
common-workload regressions outweigh the removed array traffic.

### Automatic-spacing dispatch (2026-10-22)

`ParseSession::skipSpacingSyntax()` now owns its syntax-returning path rather
than forwarding through `recognizeTrivia()`. It still shares the per-session
wrapper cache with ordinary `skipSpacing()`, suppresses recursive spacing and
restores that scope in `finally`. Prepared nullable trivia caches the two static
CST kind names once. No scanner, trivia node, child match or diagnostic is removed.

A fresh trace of core and extension copies on the 128-ID nested application
fixture with scanner spacing counted 22,816 source method calls before and
21,774 after, 1,042 fewer. `recognizeTrivia()` calls fell from 272 to 11,
`concreteKind()` calls from 827 to 307, and the 261 syntax-only results no longer
call `Span::byteLength()`. Parser entries, work, source allocation counts and
the retained 820-node CST hash agree. Instrumented times are not performance data.

Five fresh Windows/PHP 8.2.10 workers per variant used alternating order, three
warmups and 31 observations each, with PCOV disabled. Recognition/CST median of
worker medians was 5.134 versus 4.765 ms for scanner spacing and 5.279 versus
5.066 ms for repeated-character spacing. Worker-median ranges overlap in both
cases; these observations establish no consistent throughput improvement.
Preparation, source setup, values, correctness checks, serialization, result
release and collection are outside timing. Host activity was uncontrolled.

Retained recognition memory remains 388,600 bytes for scanner spacing and
389,608 for repeated-character spacing. The two cached kind references increase
each prepared wrapper's size; the observed incremental recognition peak rose
64 used bytes in each fixture. Automatic wrappers live with the session, while
explicit prepared Trivia can retain those fields with the grammar. There is no
new cache, preparation pass or asymptotic change. First wrapper preparation does
two extra kind lookups, replacing two per successful syntax-only attempt.

Original files, full-source trace reports and timing workers are retained under
ignored `build/spacing-dispatch/`, using the independent-oracle research driver
under `build/spacing-research/`. While retained, run each in fresh processes and
alternate order:

```sh
php -d pcov.enabled=0 build/spacing-dispatch/before.php --spacing=scan
php -d pcov.enabled=0 build/spacing-research/experiment.php --spacing=scan
```

Use `--spacing=set` for the repeated-character control. The existing trivia
regressions compare prepared/plain sequence, Repeat and SeparatedList behavior,
including byte/UTF-8 sources, rollback, boundaries, effects and exhausted limits.
A session regression covers custom syntax hooks throwing and falling back to
ordinary matches while sharing one cached wrapper. Reject this change if trees,
ordered diagnostics, work, scope restoration, effects or custom hooks differ,
or stable common-workload regressions outweigh the dispatch reduction.

### Composed scanner dispatch (2026-10-22)

Prepared sequences batch adjacent metadata-free `ReadWhileSet` and
`ReadWhileRange` operands using their existing `scanLength()` implementation.
Every scanner still constructs its original CST leaf, including empty leaves.
This fuses dispatch, not the scanning patterns. Metadata separates runs; active
spacing, enclosing case-insensitivity and finite work/depth limits retain the
ordinary path. Failed runs retry only their original callback-free operands for
diagnostics, so unlimited failed parses can charge additional speculative work.
Finite budgets never enter this path. Earlier executable operands are not retried.

A fresh Windows/PHP 8.2.10 comparison used five workers per variant, alternating
order, three warmups and 31 observations per worker, with PCOV disabled. The
128-ID nested application grammar used scanner spacing and composed scanner
tokens. Recognition includes CST construction; preparation, source construction,
value checks, serialization, result release and cycle collection are outside
timing. Medians of worker medians were 12.488 ms before and 11.205 ms after
(10.3% lower). Worker medians ranged 12.191–12.828 and 10.778–11.341 ms. Host
activity was uncontrolled; this is not a universal speed guarantee.

Frozen parser entries fell from 816 to 558. The 1,336-node CST hash and retained
recognition memory of 723,552 used bytes were unchanged. Preparation includes
autoload/discovery and varied; no preparation-time improvement is established.
Plans add one small grammar object and operand-reference array per eligible run;
they retain no source or per-session results. Recognition remains proportional
to scanned input and constructed syntax, with unchanged retained-tree complexity.
Short scanner compositions still retain more nodes than the regex-token grammar;
this optimization does not establish that replacing regex tokens is beneficial.

The saved pre-change Sequence, driver and raw observations are under ignored
`build/scanner-fusion/`, using the research driver in `build/spacing-research/`.
The maintained profiler now accepts `--scan-tokens`; compare matching revisions
in fresh workers with `--scan-spacing --scan-tokens --case=nested --size=128
--iterations=31`. Its parser-entry metrics use an unfrozen graph and must not be
used to infer prepared dispatch counts. Correctness tests compare same-graph CST,
AST, ordered diagnostics, byte offsets, rollback, cached values, effects, file
snapshots, scope restoration and exhausted execution limits.

### Separated-list checkpoint reuse (2026-10-22)

`SeparatedList::recognize()` reuses its loop-entry diagnostic checkpoint for an
absent optional leading separator. Only local variables are initialized between
loop entry and the separator attempt, so the second checkpoint captured identical
session state. Pair termination, bounds, spacing and deferred failures retain
their existing restoration paths.

A fresh PHP 8.2.10 instrumented comparison counted checkpoint captures on a
successful eight-item list: 17 before versus 9 after, or 18 versus 9 with leading
separators enabled. Both freeze modes produced the same counts and matching
CST/value/diagnostic fingerprints. A separate 52,992-case baseline/current
comparison matched parser outputs and execution-limit outcomes. Regression tests
cover a partial optional leading separator observing farther input before its
failure is discarded, followed by successful or required-failing item recognition.

This removes transient outer checkpoint arrays, not deep copies of their shared
contents. No state, preparation pass or asymptotic change is introduced. No
throughput or retained-memory improvement is established. The saved original
SeparatedList, counter and differential driver are under ignored
`build/list-checkpoint/`; while retained, reproduce from the repository root:

```powershell
php -d pcov.enabled=0 build/list-checkpoint/count.php --baseline
php -d pcov.enabled=0 build/list-checkpoint/count.php
php -d pcov.enabled=0 -d memory_limit=512M build/list-checkpoint/differential.php --baseline
php -d pcov.enabled=0 -d memory_limit=512M build/list-checkpoint/differential.php
```

### Deferred-list union sharing (2026-10-22)

ParseSession now retains tied, unobserved deferred-list observations as shared
`Runtime\DeferredDiagnostic` unions. Their ordered operands are logically
immutable. Reporting flattens shared branches once, caches a normal Diagnostic
with fixed arrays, and releases the materialized node's operand references.
Already observed active/deferred combinations reuse their cached output and keep
eager merging; rebuilding a lazy history on that path would add unnecessary work.
Farther observations still win without flattening nearer histories.

Flattening preserves left-before-right ordering, including both nested deferral
boundaries: the checkpoint's deferred details precede newly deferred details,
while ordinary details precede deferred details when exposed. Numeric-looking
path components retain the original pairwise `SORT_REGULAR` merges. Numeric and
lexical string comparisons can interact, so an arbitrary flattened union must
not silently substitute strict path equality or regroup those operations.
Identical normalized observations share their existing snapshot, and pending
chain depth is capped at 128 by periodic materialization. Explicit checkpoints
can still retain older histories; this is not constant-memory parsing.

Checkpoint arrays retain five fields, with the deferred field typed as
`Diagnostic|DeferredDiagnostic|null`. Save and restore them as opaque snapshots;
`diagnostic()` and `diagnostic(true)` remain the observation APIs. Public result
Diagnostics retain their existing class, ordered readonly arrays and byte offsets.
Work/depth accounting, parser delegation, selected trees, semantic caches and
explicit effects are unchanged. Cache keys publish only after materialization
succeeds, allowing a failed source check to retry without returning stale output.

A Windows/PHP 8.2.10 comparison used five fresh workers per variant in alternating
order, one warmup and 11 observations per synthetic timing case, with PCOV disabled.
Instrumented workers counted allocations and candidate entries separately; their
times are not performance evidence. These medians are medians of worker medians:

| 512 tied deferred probes | Before ms | After ms |
|---|---:|---:|
| Accumulate without exposing the final union | 140.701 | 4.726 |
| Accumulate and expose the final union | 141.680 | 5.128 |
| Repeated identical singleton details | 1.658 | 1.441 |

The distinct probes have different expectation labels and different nonnumeric
paths at one byte offset. Before, both distinct cases constructed 1,534 Diagnostics
and processed 393,470 expectation entries plus 393,470 path entries through tied
unions. After, pending accumulation constructed 526 Diagnostics and 1,022 small
union nodes, processing 5,474 entries of each kind during 14 compactions. Requesting
the final union raised that to 527 Diagnostics, 5,986 entries of each kind and
15 materializations. These are logical input slots, not allocated bytes or unique
output entries. Total object count does not decrease: cheap union nodes replace
much more expensive repeated array merging. Identical singleton probes create no
pending nodes. Numeric-path probes retain their eager operations; the 128-probe
numeric control measured 10.044 versus 10.172 ms, showing a small overhead.

The benefit depends on whether intermediate unions are observable. A longest
Choice over 512 nullable SeparatedLists, followed by a successful literal, creates
tied termination failures while succeeding. With `FreezeFlags::NONE`, its
Diagnostic count fell from 1,534 to 526 and expectation input slots from 393,470
to 5,474. Three fresh timing workers per variant measured 16.367 versus 12.068 ms;
a trailing-input failure variant measured 17.091 versus 12.189 ms. The worker
ranges did not overlap in these synthetic cases. With default optimization, failed item metadata
already exposes each union: the cache-reuse path retains the original 1,534
Diagnostics and input-slot counts. A focused five-worker comparison with 51
observations per worker measured 17.153 versus 17.289 ms for success, with
overlapping ranges. Do not infer the isolated
session fixture's roughly 30-fold gain for ordinary application parsing.

The nested application control used five fresh workers and 101 observations each.
Recognition medians were 4.464 versus 4.457 ms with overlapping worker ranges;
retained recognition memory remained 389,704 bytes and the CST hash was unchanged.
No application throughput improvement or consistent regression is established.

Separate memory workers measured PHP used-byte deltas above each iteration's
pre-call baseline, with the source and grammar already prepared.
Keeping the 512-probe session pending retained 219,592 versus 220,376 bytes; peak
usage rose from 289,336 to 348,312 bytes. Exposing the final Diagnostic retained
168,208 bytes in both versions, but still incurred the higher peak. Shared nodes,
traversal bookkeeping and materialization chunks trade temporary memory for less
repeated union work. The default-optimized successful grammar retained 1,144 bytes
and peaked at 425,160 bytes in both versions. These are neither RSS nor cumulative
allocation measurements.

There is no new freeze pass or retained grammar cache; first use can autoload the
new runtime class. Cold preparation/first-parse differences were not established.
Flattening visits shared branches once per materialization, but candidate arrays
still need merging/deduplication. Periodic compaction and repeatedly observed
growing prefixes can still produce quadratic total traffic. Numeric paths keep
the original complexity. No general linear-time or bounded-total-memory guarantee
is introduced.

Baseline/current fingerprints matched 52,992 parser cases and 14,306 randomized
session observations, including checkpoint forks, restore/relabel/defer ordering,
numeric and binary paths, byte/UTF-8 sources, both freeze modes, all choice policies,
values, effects and work/depth exceptions. Focused tests cover shared branches,
operand reuse, immediate operand release, duplicate/long histories, numeric
comparison boundaries and retry after failed materialization. Reject the change
if ordered payloads, rollback, retained snapshots or limit outcomes differ; if
pending memory exceeds the application's budget; or if stable common-workload
regressions outweigh the gains on deferred-heavy inputs.

Saved original core/extension sources, drivers and raw reports are under ignored
`build/deferred-unions/`. While retained, run each variant in fresh processes,
alternating order; add `--baseline` to select the saved original sources:

```powershell
php -d pcov.enabled=0 -d memory_limit=512M build/deferred-unions/measure.php --iterations=11
php -d pcov.enabled=0 -d memory_limit=512M build/deferred-unions/measure.php --trace
php -d pcov.enabled=0 -d memory_limit=512M build/deferred-unions/measure.php --memory
php -d pcov.enabled=0 -d memory_limit=512M build/deferred-unions/measure.php --no-optimize --case=grammar --iterations=11
php -d pcov.enabled=0 -d memory_limit=512M build/deferred-unions/session-differential.php
php -d pcov.enabled=0 -d memory_limit=512M build/deferred-unions/differential.php
php build/deferred-unions/summarize-published.php
```

Correctness checks and serialization are outside synthetic timers and memory
observations. Grammar preparation is separate; sessions/parse results are returned
from timed calls, and result release occurs afterward. The pending-session memory
case deliberately retains that session before observing its final union. Instrumented
counts stop before correctness inspection. `profile.php` wraps the maintained
extraction profiler with matching extension sources; baseline runs also pass
`--source-dir=build/deferred-unions/baseline/src`. Final timing artifacts are
`summary-published.jsonl`, `summary-control.json` and `summary-none.json`;
earlier exploratory runs remain alongside them. Host activity is uncontrolled.

### Discarded terminal failure snapshots (2026-10-22)

The shared checked recognition path now permits exact built-in Choice, Repeat
and SeparatedList consumers to omit Diagnostic snapshots on terminal failures
whose records they discard without inspecting. Ordinary recognition still records
every failure, expectation and rule path. The owning combinator materializes the
observable diagnostic, including deferred list failures and relabeling. Successful
child records and guard rejections retain their snapshots. Custom terminals,
combinator subclasses and other consumers retain eager metadata by default.
The eligible ordinary terminals are exact Any, Char, Range, Regex, Set and Text
classes; overriding their failure hooks does not opt a subclass in.

Optimizer Choice and Repeat apply the same rule to lexical-table replay. First
observations still materialize diagnostics needed to prepare relative recipes.
No new recognition entry point, lazy public Diagnostic proxy, persistent history,
session cache or grammar preparation pass is introduced. Byte progress remains
available without constructing an active/deferred diagnostic union. The internal
delegation option is local to one attempt, so it requires no mutable scope to
restore after exceptions. Deferred-list accumulation and checkpoint representation
are unchanged.

A fresh Windows/PHP 8.2.10 comparison instrumented source copies separately from
time and memory workers, with PCOV disabled. A fixture tries 512 distinct failing
Text alternatives before accepting `az`; repeat and list fixtures accept eight
items. Warmed frozen optimization produced these Diagnostic construction counts:

| Successful fixture | Before | After |
|---|---:|---:|
| Choice | 512 | 1 |
| Repeat of Choice | 10 | 9 |
| SeparatedList of Choice | 4,098 | 10 |

The already optimized repeat has little additional opportunity. The nested
application-value fixture still constructs four Failures and four Diagnostics,
retains 822 CST nodes and uses 389,704 bytes after recognition. There is no
application allocation reduction in that control.

Separate warmed memory workers measured the 512-alternative choice's PHP used
recognition peak at 73,888 versus 61,816 bytes above the prepared baseline.
Retained result memory was unchanged at 14,080 bytes. The 128-alternative list
fixture's peak was 56,552 versus 54,320 bytes, with unchanged retained memory of
37,480 bytes. These are used bytes, not RSS, process allocation peaks or cumulative
allocation. Temporary object elimination does not remove retained CSTs or selected
semantic state.

Timing used five fresh workers per variant, alternating order, three warmups and
101 observations per worker. One run's median of worker medians improved the
512-alternative choice from 2.952 to 2.209 ms; a follow-up measured 3.188 versus
3.252 ms with widely overlapping worker ranges. The nested control measured
5.317 versus 5.344 ms initially, and 7.521 versus 5.208 ms in the follow-up, again
with overlapping ranges. Tiny literal/sequence controls varied too. These noisy
runs establish no consistent throughput improvement or regression. Do not infer
application speedups from allocation counts or instrumented timings.

There is no additional retained grammar or session data structure. Preparation
complexity is unchanged; a cold preparation-time difference was not established.
Recognition still visits the same parsers and records the same failures. Avoided
snapshots reduce temporary allocation and some copy-on-write pressure, while
ordered expectation/path accumulation and observable array materialization remain.
Selected successful children can still retain different historical snapshots.

A 52,992-case baseline/current comparison matched acceptance, byte consumption,
ordered diagnostics with formatted locations/excerpts, CST/AST, cached values,
explicit effects and exact work/depth exception offsets and paths. Cases include
byte/UTF-8 sources, nonzero entry positions, all choice policies, both freeze modes,
labels, malformed nested lists and optional/lookahead rollback. Regression tests
cover successful/guard-rejected operands, retained custom terminal failures,
combinator subclass hooks through cold and warmed lexical tables, immutable
observations and deferred farthest offsets. The full runtime suite also passes.
Reject this optimization if any observer loses metadata, a retained snapshot
changes, failure ordering or rollback differs, effects execute speculatively,
limit accounting changes, or reproducible common-workload regressions outweigh
the temporary allocation benefit.

The original sources, experiment drivers and raw reports are under ignored
`build/deferred-attempts/`. While retained, reproduce baseline and current in fresh
processes, alternating their order:

```powershell
php -d pcov.enabled=0 -d memory_limit=512M build/deferred-attempts/measure.php --baseline
php -d pcov.enabled=0 -d memory_limit=512M build/deferred-attempts/measure.php
php -d pcov.enabled=0 -d memory_limit=512M build/deferred-attempts/measure.php --memory
php -d pcov.enabled=0 -d memory_limit=512M build/deferred-attempts/experiment.php --trace --frozen-opt
php -d pcov.enabled=0 -d memory_limit=512M build/deferred-attempts/differential.php --baseline
php -d pcov.enabled=0 -d memory_limit=512M build/deferred-attempts/differential.php
```

Add `--baseline` to trace or memory commands for the saved source snapshot.
`profile.php` wraps the maintained extraction profiler with matching extension
sources; baseline workers additionally pass
`--source-dir=build/deferred-attempts/baseline/src`. Reports under `idle/` retain
the follow-up timings. Neither run controls unrelated host activity.

### Internal effect lifetimes (2026-10-22)

`Parser::bindCommitEffects()` now stores a `CommitEffects` registration snapshot
instead of a closure capturing its owning Success. `CallbackTrait` supplies that
owner only when executing the binding. Ordinary custom callbacks retain their
zero-argument contract and position in the same callback list. Assignments still
precede effects; one ParseResult wrapper is shared by the effects in each binding.
No additional ExecutionState fields, weak references or per-recognition caches
are needed. One binding object replaces one closure; this is primarily a lifetime
improvement, not a reduction in the number of objects constructed.

A fresh local Windows/PHP 8.2.10 comparison used 128 successful longest-choice
alternatives, each with a cached constant mapping and a no-op commit effect.
Separate instrumented source copies counted allocations and live objects with GC
disabled. Both versions created 129 Successes, 129 ExecutionStates, 128 Spans,
129 ConcreteNodes and 128 SemanticProjections. Before explicit GC, live Successes
fell from 129 to 2, live projections from 128 to 1, and live nodes from 129 to 2.
The new version created 128 binding objects, with only one still live at return;
the old trace excludes its 128 closure allocations. After GC the selected graphs
have the same object counts. Existing span reuse, sparse state and projection
caching remain in place.

Uninstrumented memory workers measured these used-byte deltas from the prepared
grammar/source baseline, consistently across five workers and 21 observations:

| Phase | Before | After |
|---|---:|---:|
| Recognition return, before explicit GC | 245,104 | 2,632 |
| Recognition return, after explicit GC | 3,296 | 2,632 |
| Recognition peak | 291,400 | 50,832 |
| After value read and result release, before GC | 1,904 | 0 |
| After final GC | 0 | 0 |

Elapsed time was measured separately without tracing or memory-phase GC. The
first five-worker run was noisy and appeared slower. A follow-up with 11 fresh
workers per variant, alternating order, three warmups and 101 observations gave
median recognition times of 0.955 / 0.946 ms. Worker-median ranges overlap
(0.855–1.191 / 0.866–1.530 ms). The no-effect control took 0.876 / 0.866 ms,
also overlapping. These results establish no throughput improvement. Commit
throughput was not measured; dispatch adds an instanceof check per callback.
Preparation retains the same grammar registrations, with one new class to autoload;
cold preparation-time differences were not established. Asymptotic recognition,
tree retention and commit complexity are unchanged. Destruction work moves from
cycle collection to reference release during recognition.

CST/diagnostic fingerprints matched across variants and measurement modes.
Regression tests disable cycle collection and check immediate source/record
release, losing alternatives, registration snapshots, assignment order, shared
effect wrapper identity, zero-argument callbacks and independent commits. Existing
tests cover cached exceptions, partial commit failure, validation and recovery.
User callbacks, cached values and exception traces may still form cycles. Reject
this change if selected state is released early, speculative effects execute,
snapshots/order/errors change, or stable representative workloads show an
unacceptable recognition or commit regression.

The saved source baseline, driver and raw reports are under ignored
`build/effect-cycles-20261022/`. While those artifacts remain, reproduce each
phase separately (use `--source-root=build/effect-cycles-20261022/baseline` for
the original version), alternating variants in fresh processes:

```powershell
php -d pcov.enabled=0 build/effect-cycles-20261022/experiment.php --case=ranked-effects --size=128 --mode=trace --iterations=1
php -d pcov.enabled=0 build/effect-cycles-20261022/experiment.php --case=ranked-effects --size=128 --mode=memory --iterations=21
php -d pcov.enabled=0 build/effect-cycles-20261022/experiment.php --case=ranked-effects --size=128 --mode=time --iterations=101
```

### Single-unit terminal allocations (2026-10-22)

Exact built-in Char, Range, Set and Any terminals now reuse a successful
`Source::readUnit()` span instead of constructing an equal match span. Both success
factories use the same metadata, collection and effect binding code. Subclasses
retain the existing `success()` and `makeMatch()` hooks. Char validates its literal
encoding directly; construction already established its unit count. Range bounds
and Set membership preparation likewise validate strings without temporary
Utf8Source objects. Invalid literals still fail at recognition, including EOF.

A local Windows/PHP 8.2.10/PCRE 10.40 comparison used complete saved original
core/extension sources and the changed checkout. It isolates this change. Separate
allocation traces covered copies of both trees; elapsed time and memory came from
uninstrumented workers with PCOV disabled. Fourteen scenarios used five fresh workers
per variant in alternating order, three warmups and 21 timed observations; separate
memory workers used three observations. Grammar/source preparation, correctness
checks, serialization, result release and explicit GC were outside recognition
timers. Medians of worker medians for 1,024-unit repeats:

| Scalar terminal/source | Recognition ms before / after | Source Span allocations before / after |
|---|---:|---:|
| Char, Unicode UTF-8 memory | 7.112 / 6.194 | 2,050 / 1,026 |
| Char, ASCII UTF-8 memory, optimization disabled | 6.754 / 6.094 | 2,050 / 1,026 |
| Char, byte memory, optimization disabled | 6.129 / 5.567 | 2,050 / 1,026 |
| Range, Unicode UTF-8 memory | 7.010 / 6.790 | 2,050 / 1,026 |
| Set, Unicode UTF-8 memory, optimization disabled | 6.593 / 6.260 | 2,050 / 1,026 |
| Any, UTF-8 memory, optimization disabled | 5.481 / 5.190 | 2,050 / 1,026 |
| Char, Unicode UTF-8 file | 10.115 / 9.574 | 2,050 / 1,026 |

The Unicode Char fixture also removed 1,025 temporary Utf8Sources, including its
EOF attempt. Warm Range/Set already cached their validation, so their corresponding
source counts were zero in both recognition traces. Counts describe source `new`
expressions, excluding PHP internals, arrays and closure creation. The resulting
CST still has 1,025 nodes and retains 1,025 spans. Retained recognition memory
remained 316,184 used bytes and the parse peak remained 535,448 bytes in the Char
fixture: this removes short-lived objects, not the retained tree/execution graph.
First/cached value allocation and semantic behavior are unchanged.

The larger first-run application controls were noisy, so a separate comparison used
11 workers per variant and 101 parses each. Unicode Char recognition took 7.429
versus 6.624 ms (about 11% faster), with nonoverlapping worker-median ranges in that
run. Nested application recognition took 4.395 versus 4.476 ms, the record took
0.651 versus 0.645 ms, and an unoptimized Text repeat took 5.730 versus 5.818 ms.
Those control ranges overlap; the added common binding call may impose a small
cost on ordinary successes. No general application speedup is established. Already
optimized ASCII/scanner repetitions keep their existing allocation counts and have
little to gain from this scalar change. Preparation adds no caches or state fields,
and recognition/tree-retention complexity is unchanged.

Baseline/current workers agreed on CST/diagnostic hashes and all 21 application
acceptance/domain oracles. A separate 8,968-case comparison matched byte/UTF-8
sources, every tested byte position, include/exclude and case policies, invalid
literals, both freeze modes, metadata, collections, values, assignments, explicit
commit, AST/CST output, formatted diagnostics and exact work/depth failures.
Regression tests also cover custom forwarding sources, subclass hooks and scope
restoration on exceptions, page-crossing file units, closed snapshots, zero-width
failures and independent recognitions sharing one immutable source span.

The source snapshot, drivers and raw reports are under ignored
`build/single-unit-allocations-20261022/`. While those artifacts remain:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File build/single-unit-allocations-20261022/run.ps1
php build/single-unit-allocations-20261022/summarize.php
powershell -NoProfile -ExecutionPolicy Bypass -File build/single-unit-allocations-20261022/run-controls.ps1
php build/single-unit-allocations-20261022/summarize-controls.php
```

### Nullability preparation (2026-10-21)

Graph analysis now records lookup dependencies during each stable nullability
round and schedules calculations whose observed estimates changed. This includes
self-reads, custom reads of collected descendants and operands discovered as
short circuits open. Updates are published after the round's lookup is released,
avoiding an entire estimate-map copy on each round. The conservative ordering
false < unknown < true, progress checks and leading-cycle checks still apply.
The dependency tables are temporary; frozen grammars retain no analysis cache.

A local Windows/PHP 8.2.10 comparison loaded saved original GraphAnalysis and
NullabilityLookup classes or their changed versions against the same remaining
checkout. Each of 32 scenarios used three fresh workers per variant in alternating
order, a first-use freeze, two warmups and five timed observations. PCOV was
disabled. Builder construction, source setup, recognition, correctness checks and release
were outside freeze timers. Timed phases separated snapshot copying/resolution,
analysis and optimizer preparation, alongside complete `Parser::freeze()` calls.
Deferred factory construction remains part of snapshot copying/resolution.
Medians of warmed worker medians, with default optimization enabled:

| Grammar | Complete freeze before (us) | After (us) |
|---|---:|---:|
| One Text terminal | 4.8 | 5.2 |
| 128 nested nullable Sequences | 8405.7 | 3586.2 |
| 1,024 nested nullable Sequences | 396236.8 | 46157.1 |
| 512 branches sharing a nullable chain | 21388.7 | 16888.0 |
| RFC URI | 2880.4 | 2687.1 |
| Mail address list | 1172.2 | 1138.8 |
| XML document | 3165.2 | 2982.4 |
| JSON text | 456.5 | 459.8 |
| Application-value extraction | 94.2 | 91.4 |
| 512 consuming sequence operands | 4096.6 | 4375.8 |

Both `FreezeFlags::NONE` and `OPTIMIZE` were measured. With optimization disabled,
the 1,024-wrapper freeze took 363.31 versus 19.32 ms. URI analysis alone took
0.883 versus 0.557 ms without optimization, and 0.890 versus 0.578 ms with it.
JSON and extraction changes were small; the one-terminal and consuming controls
show overhead without a reduction in nullability calculations. Cold samples are
retained separately: initial freezes can include provider discovery and class
loading, while some authoring paths incur those costs during construction.
No general cold-start or warmed recognition speedup is claimed.

Separate instrumented runs counted all nullability calculations on the
1,024-wrapper graph: 1,051,650 before and 2,049 after; the Sequence-only counts
are 1,050,624 and 2,048. Fixed-point rounds were still 1,026 versus 1,025: the
gain comes from avoiding whole-graph visits each round. URI calculations fell
from 1,750 to 281, and the 512-branch shared graph from 10,600 to 1,059.
Instrumented times are not used as performance measurements.

The reverse dependency map costs O(V + E) temporary storage for observed reads.
Each estimate increases at most twice, bounding scheduling notifications by
O(E); calculation cost still depends on operand scans and custom hooks. A simple
chain now needs O(V) nullability calculations, but collected paths and cycle
traversal still have quadratic costs on deep chains. The optimized URI analysis
used-memory peak rose from 176,264 to 230,376 bytes; the 512-operand nullable
sequence rose from 312,296 to 485,576 bytes. The deep-chain analysis peak stayed
about 44.3 MB because paths dominate. These are incremental PHP used-memory
peaks above prepared snapshot baselines, not process allocation peaks or RSS.
Retained frozen-grammar memory was unchanged in these scenarios.

All workers checked independent acceptance and source round trips, with exact
application values for extraction. Cold/warm frozen graphs and the phase-split
prepared roots produced matching CST, AST, value and diagnostic fingerprints
across variants. Library inputs included valid/malformed cases and generated
sizes 1/8/64 (repetitions or payload length); this preparation change does not
depend on input length. The same
targeted safety tests passed against original and changed classes. Regression
coverage includes stable-round reads, unknown propagation, changing read
dependencies, non-monotone self-reads, recursion and both freeze modes.

The driver, original classes, raw cold/warm observations, trace copies and summary
are under ignored `build/nullability-preparation/`. While those snapshots remain:

```sh
php -d pcov.enabled=0 -d memory_limit=512M build/nullability-preparation/measure.php
php -d pcov.enabled=0 -d memory_limit=512M build/nullability-preparation/measure.php --trace
php -d pcov.enabled=0 build/nullability-preparation/summarize.php
```

Three workers and small timings have host/allocator noise. Reject the optimization
if safety decisions, warnings, paths, snapshot isolation or deferred operations
differ, or if the application's preparation/memory cost regresses beyond its
budget. Large chain gains do not imply gains for already shallow or reused graphs.

### Bounded diagnostic scanning (2026-10-21)

`Diagnostic::location()` now scans only its source prefix, with one-byte
lookahead when that prefix ends in CR. `excerpt()` searches forward for the
current line's terminator in 8 KiB chunks. A diagnostic retains its coordinates
and line boundaries, replacing the per-diagnostic array of every line start.
Repeated locations retain the closed-file check without rereading prefix bytes.
Default array/JSON output continues to read no source bytes.

A local Windows/PHP 8.2.10 comparison loaded the saved original Diagnostic or
the changed class in fresh workers against the same remaining checkout. PCOV
was disabled. Each of 32 scenarios used three workers per variant in alternating
order, a separate first-use observation, two warmups and seven timed observations.
The sources were in-memory and file-backed UTF-8; fixtures had 64, 4,096, 65,536
or 350,000 short CRLF lines, or a 64 KiB/1 MiB Unicode line. Offsets covered the
start, middle and EOF. Source preparation, fixture construction, assertions and
release were outside formatting timers. Medians of warmed worker medians:

| Source and requested position, 1,050,001-byte short-line fixture | First location before | After |
|---|---:|---:|
| UTF-8 memory, start | 62.07 ms | 0.0007 ms |
| UTF-8 memory, middle | 62.42 ms | 24.82 ms |
| UTF-8 memory, EOF | 63.17 ms | 49.41 ms |
| UTF-8 file, start | 63.08 ms | 0.0008 ms |
| UTF-8 file, middle | 64.04 ms | 26.09 ms |
| UTF-8 file, EOF | 64.01 ms | 50.09 ms |

All workers matched independent expected locations, excerpts, messages and JSON;
before/after output fingerprints agreed in every scenario. Separate read-counting
workers requested 1,050,001 logical bytes for the original byte-zero location and
zero nonempty bytes afterward. Requesting its first excerpt then read 8,194 bytes
(one search window and the two-byte line). These are Source slice requests, not
physical disk I/O. Instrumented timings are not used as performance data.

Location retained used memory on that in-memory fixture fell from 10,486,344 to
536 bytes, measured above prepared-source baselines. For the single-line fixture,
it rose from 504 to 536 bytes. These deltas include the diagnostic object and
returned coordinates; they are PHP used bytes, not allocated process peaks or
RSS. No preparation or parsing speedup is claimed.

First location remains O(requested byte offset), with O(1) retained position
metadata. First excerpt additionally scans the remaining current line and copies
the requested line; repeated excerpts still copy it. A first excerpt after a
known location can cost more than the old index lookup: the file/middle case
above took 15.6 versus 10.8 us. The 1 MiB Unicode line at EOF showed no meaningful
first-location improvement (memory: 53.51 versus 53.44 ms; file: 54.35 versus
54.25 ms). Cached location access avoids recounting long prefixes (memory/EOF:
1.95 ms to 0.00044 ms in that fixture). Small timings and three workers are noisy;
these measurements establish bounded reads and workload-specific gains.

The driver, original class snapshot, raw observations and summary are under ignored
`build/diagnostic-line-scanning/`. While that snapshot is retained, reproduce with:

```sh
php -d pcov.enabled=0 -d memory_limit=512M build/diagnostic-line-scanning/experiment.php
php -d pcov.enabled=0 -d memory_limit=512M build/diagnostic-line-scanning/experiment.php --reads
php -d pcov.enabled=0 build/diagnostic-line-scanning/summarize.php
```

Timing workers separately measure construction, first/cached location access,
first/cached excerpt after location, and JSON after both caches are populated.
The first-use observation follows source setup and is not cold disk I/O.
Regression tests cover every byte position in mixed-newline/Unicode/binary
fixtures, CRLF at chunk/page boundaries, read bounds, failed-read retry and
closed snapshots. Any changed coordinates, excerpt bytes, source-lifetime
behavior or unbounded early-position reads would reject this optimization.

### Automatic trivia syntax (2026-10-21)

Eligible prepared sequence, repeat and separated-list implementations now retain
nullable automatic trivia as its original immutable syntax without constructing
a temporary wrapper execution record. Required-minimum trivia, metadata, custom
classes, explicit trivia and finite work/depth limits keep their existing paths.
The public session `skipSpacing()` method still returns independent matches.

A local Windows/PHP 8.2.10 comparison used complete core/extension snapshots,
five fresh workers per variant/scenario, alternating before/after order, three
warmups and 30 timed parses per worker (seven for the 1,024-ID fixture). PCOV was
disabled. Timings include fresh UTF-8 source setup, complete recognition/CST and
the first domain value. Preparation, assertions, serialization, result release
and cycle collection are outside timing. Medians of worker medians, microseconds:

| Fixture | Before | After | Speedup |
|---|---:|---:|---:|
| Nested 128 IDs, repeated-character spacing | 5453.8 | 4513.2 | 1.21x |
| Nested 128 IDs, scanner spacing | 5010.4 | 4206.1 | 1.19x |
| Late malformed nested list, scanner spacing | 5060.6 | 4170.8 | 1.21x |
| Nested 1,024 IDs, scanner spacing | 40396.3 | 32447.8 | 1.24x |

Every application worker checked all 21 independent oracle cases, and all
before/after workers agreed on CST/diagnostic fingerprints. No-spacing sequence
and repeat controls were approximately unchanged. A longer small-record run
used 15 workers per variant and 100 parses each: repeated spacing took 785.8
versus 751.9 us; scanner spacing took 713.6 versus 716.2 us. The latter is
effectively neutral. Host variation and small-input overhead preclude a universal
speed claim. Cold/preparation observations are retained in the raw report;
class loading and extension discovery affect those costs, and no preparation
speedup is established.

A separate trace of both core and extension methods on the scanner-spacing
128-ID fixture counted 429 to 168 checked parser entries and 425 to 164 Success
allocations. ConcreteNode allocations remained 826, spans remained 555, and the
retained tree still had 820 nodes and the same serialized hash. Retained used
memory after value access was unchanged at 392,536 bytes. Incremental used-memory
parse peaks fell from 449,312 to 400,480 bytes; the 1,024-ID peaks fell from
3,338,016 to 2,896,032 bytes. These are PHP used bytes above a warmed baseline,
not allocated process peaks or OS RSS. The rewrite reduces temporary records,
while CST retention and recognition complexity remain unchanged.

The driver, full snapshots and reports are under ignored
`build/trivia-record-optimization/`. Run
`php -d pcov.enabled=0 -d memory_limit=512M build/trivia-record-optimization/measure.php`
to repeat the timing comparison while that local snapshot is retained. Trace
workers use `--worker --variant=before|after --case=nested --spacing=scanner
--iterations=1 --trace` separately; instrumented times are not performance data.

### Prepared trivia and direct list punctuation (2026-10-21)

Prepared plain trivia can scan its operand and construct the same child syntax
without a temporary child execution record. Eligible ASCII literal list separators
can likewise create their final CST node directly. The ordinary list loop retains
bounds, rollback and lazy item values. Custom/decorated operands, deferred trivia
and finite execution limits retain the corresponding ordinary paths. See
[grammar optimization](grammar-authoring.md) for eligibility and fallbacks.

Local Windows/PHP 8.2.10 measurements compare saved source and extension snapshots
with whitespace specialization alone and both changes. Each variant/case used six
fresh workers in rotating/reversed order, three warmups and 30 timed parses per
worker. The table reports medians of worker medians, in microseconds. Parsing
starts from a string, requires complete input and obtains the domain value;
preparation, assertions, result release and cycle collection are outside timing.
PCOV is disabled. The nested fixture contains 128 generated IDs.

| Fixture | Before | Trivia alone | Both changes | Combined speedup |
|---|---:|---:|---:|---:|
| Record, repeated-character spacing | 773.6 | 671.1 | 704.8 | 1.10x |
| Record, scanner spacing | 711.3 | 626.8 | 637.1 | 1.12x |
| Nested list, repeated-character spacing | 7338.4 | 5840.2 | 5482.4 | 1.34x |
| Nested list, scanner spacing | 6807.0 | 5579.1 | 5189.2 | 1.31x |

Every worker checked all 21 independent acceptance/output oracle cases. CST and
diagnostic fingerprints agreed across all workers and variants. Retained CST
counts and used-memory deltas were unchanged. The list specialization improved
the larger fixture beyond trivia alone, but its extra list bookkeeping made the
record slightly slower than trivia alone in this run. These workload-specific
measurements do not establish universal gains or individual CPU attribution.
Separate traces on the scanner-spacing nested fixture counted 830 parser entries
before and 429 after, and 826 temporary Success allocations before and 425 after.
The retained tree still has 820 nodes and the same serialized hash. Tracing covers
core source methods, adds overhead and is used for call/allocation counts only;
new expressions in uninstrumented extension methods are not included.
The local driver, snapshots and raw reports are under ignored
`build/trivia-cst-optimization/`.

The [Extraction application-value comparison](extraction-comparison.md) checks two
independent grammars against one output oracle and records extraction timings,
memory retention, diagnostics and static-analysis observations. It includes
Comprehend's mandatory CST cost and an isolated optional dependency.

### Runtime allocation and dispatch (2026-10-20)

Optimized repetition now creates its original per-unit CST nodes directly. Its
shared scan primitive returns acceptance and byte progress without constructing
a discarded scanner tree or temporary child execution records. Parser metadata
and deferred operations on the repeat still use the ordinary preparation path.

Sequence preparation compiles adjacent eligible literal runs around ordinary
operands such as regexes and decorated parsers. Failed runs retry only their own
original operands, preserving diagnostics without repeating earlier recognition
callbacks. Active spacing, case-insensitive scopes and finite work/depth limits
retain the ordinary sequence path.

Diagnostic accumulation keeps ordered expectation/path lists with membership
indexes. Restoring a changed checkpoint invalidates the corresponding index;
the next insertion rebuilds it from the restored list. Binary labels, strict path
identity, insertion order and the existing checkpoint shape remain intact.
Semantic projections also consult their value/error cache before constructing a
callback-facing ParseResult. Nulls, object identity, recursive evaluation errors
and explicit commit behavior retain their existing contracts.

Local Windows/PHP 8.2.10 measurements compare a saved source snapshot with these
four changes together. Each case/variant used five fresh workers, alternating
before/after order. Workers used two warmups and seven measured batches; the table
reports the median of worker medians. Parse fixtures use in-memory byte sources.
Preparation and source construction are excluded; parsing includes full CST
construction, acceptance checks and result release.

| Fixture | Before | After | Speedup |
|---|---:|---:|---:|
| Repeat 4,096 set characters, original character nodes retained | 8.271 ms | 3.677 ms | 2.25x |
| 32 literal children, regex, 32 literal children | 0.405 ms | 0.152 ms | 2.66x |
| Accumulate 4,000 distinct expectations and paths at one position | 205.025 ms | 3.858 ms | 53.15x |
| One cached scalar value read | 0.664 us | 0.305 us | 2.18x |
| Nested application-value fixture, recognition and CST | 9.896 ms | 8.625 ms | 1.15x |

Scanner/mixed batches contain 20 parses, nested batches five parses, diagnostics
one accumulation and cached-value batches 100,000 reads. The diagnostic fixture
isolates accumulation before requesting its Diagnostic; it does not model the
cost of creating and retaining a diagnostic snapshot after every insertion.
Cached-value evaluation is warmed before measurement. Parse CST hashes agree
across all workers and variants, and the nested fixture checks expected domain
values outside timing. These synthetic, combined measurements do not establish
individual attribution, a general throughput guarantee or a preparation-time win.

Separate traces over copies of both source and extension methods counted 4,098
Success allocations before and one after for the repeated-character fixture.
Its retained tree still contains 4,097 nodes; ConcreteNode allocations fell from
4,098 to 4,097. Ten warmed value reads allocated ten ParseResult wrappers before
and zero after. Tracing adds overhead and is used only for allocation evidence.
The fixture driver, source hashes, worker reports and traces are saved locally
under ignored `build/architecture-optimizations/`.

### Recursive literal sequences (2026-10-20)

The optimizer can compile eligible nested literal sequences and fixed repetitions
to one internal `Text` recognizer, retaining every original CST node through
source-free templates. See [grammar optimization](grammar-authoring.md) for the
eligibility rules and fallbacks. This reduces parser dispatch and temporary match
records; it does not remove CST allocation or change character-node granularity.

Local Windows/PHP 8.2.10 measurements compare the previous optimizer with literal
compilation enabled, rather than comparing all optimization against `NONE`:

| Fixture | Bytes | Previous optimizer ms | Literal compilation ms | Speedup |
|---|---:|---:|---:|---:|
| Flat sequence of 512 one-byte texts | 512 | 3.460 | 0.908 | 3.81× |
| 16 sequences of 32 one-byte texts | 512 | 3.666 | 0.601 | 6.10× |
| Exact repeat of 256 `ab` texts, then `!` | 513 | 1.126 | 0.297 | 3.79× |

Each result is the median of three fresh-worker medians. Before/after worker order
alternated; workers used two warmups and seven batches of 20 parses per prepared
grammar. Timings include recognition, full CST construction, acceptance checks and
result release, and exclude source construction and grammar preparation. CST
hashes matched across all workers and both optimizer versions for each fixture.
The benchmark also checked the unoptimized CST and alternated unoptimized/optimized
batch order within each worker. These synthetic results are not general latency
guarantees; they do not establish gains for variable mail or XML grammars.

Compilation adds preparation work: optimized preparation medians increased from
30.55 to 34.58 ms for the flat fixture, 1.49 to 9.81 ms for nested sequences, and
1.15 to 3.03 ms for the fixed repeat. The first fixture also includes cold optimizer
class loading. Reuse frozen grammars to amortize classification and template
creation. Raw local reports are saved under ignored `build/performance-review/`.

The specialized benchmark used for these historical measurements has been removed.

### Library lexical tables (2026-10-17)

Freeze-time optimization now configures bounded lexical tables after references
are resolved and graph safety checks pass. Repeat operands, Choice alternatives
and Not probes can reuse source-free syntax/diagnostic recipes for known finite
ASCII patterns. Named-rule nodes, selected branches and diagnostics remain intact.
The first observation uses the original runtime; executable metadata and custom
parsers always execute normally. Tables hold at most 128 entries per operand,
with at most 32 bytes of proven lookahead. `FreezeFlags::NONE` disables them.

The historical benchmark ran each case/flag pair in a fresh PHP worker. It recorded grammar
preparation, first-parse time, warm min/median/max time, retained memory, allocated
process peak and warmed parser visits. Before accepting results it checked complete
consumption and identical CST/diagnostic fingerprints across flags and between
the first and warmed parses. Recognition includes full CST construction; source
construction and serialization used for verification are outside the timed loop.
The specialized benchmark has been removed.

Measured on Windows with PHP 8.2.10, validated in-memory UTF-8 sources, seven samples
of 30 parses per prepared grammar:

| Case | Bytes | NONE median ms | OPTIMIZE median ms | Speedup | Warm parser visits, NONE → OPTIMIZE |
|---|---:|---:|---:|---:|---:|
| Named URI port digits | 300 | 4.215 | 1.682 | 2.51× | 604 → 2 |
| ABNF quoted text / range choice | 322 | 9.264 | 2.323 | 3.99× | 890 → 7 |
| URI with repeated percent escapes | 468 | 19.289 | 5.751 | 3.35× | 2260 → 147 |
| URI with varied hashed path segments | 343 | 13.412 | 8.054 | 1.67× | 1699 → 521 |
| XML with a long CDATA section | 445 | 29.549 | 25.349 | 1.17× | 2764 → 1895 |
| Mail address list | 322 | 26.208 | 26.508 | 0.99× | 3432 → 3432 |
| JSON array | 121 | 5.243 | 5.165 | 1.02× | 623 → 623 |
| IPv6 `2001:db8::1` | 11 | 5.441 | 2.636 | 2.06× | 741 → 270 |

These are workload-specific warm results, not general speed guarantees. The mail
and JSON list controls are effectively unchanged: variable-size items retain
their original loops; no automatic SeparatedList substitution or separator-loop
fusion was added. XML still runs its character guards; finite delimiter probes
and ASCII regex alternatives account for its smaller improvement. IPv6 retains
its original ordered/ranked forms, while their finite group patterns reuse recipes.

Tables trade memory and initial classification work for repeated recognition.
Relative post-warm retained memory increased by about 20 KB for the port case,
49 KB for ABNF, 199 KB for escaped URIs, 339 KB for varied URIs, 45 KB for XML and
163 KB for IPv6; process peaks were unchanged or up to 2 MiB higher. These process
measurements include lazy class/runtime initialization as well as table storage.
The cold IPv6 parse took 18.685 ms optimized versus 9.500 ms without optimization;
its preparation also increased in this run. The JSON report retains these cold
and preparation measurements. Prefer explicit frozen-grammar reuse for these
warm gains, or disable optimization when a single parse and minimum retention
matter more. Non-ASCII lookahead, active spacing and work/depth limits use the
ordinary recognition path.

### Small facades and URI grammar (2026-10-16)

Owned built-in facade graphs now retain one prepared FrozenGrammar per immutable
expression. Deferred rules, Definition implementations and custom classes without
an explicit [reuse capability](source-and-extension-contract.md#optional-preparation-reuse)
prepare freshly. No recognition results or semantic/effect state are shared by
this cache. Session setup also reuses immutable default limits, recursion tracking
uses integer parser/position keys, default diagnostic labels are cached, and plain
leaf execution state avoids empty child filtering. Already-frozen tiny parsers
showed no substantial, consistent isolated speedup from these smaller changes.

The URI grammar uses cheaper lexical implementations for `unreserved`, `scheme`,
`h16` and `dec_octet`, with the original combinator rules preserved in comments.
The accompanying RFC fixes cover IPv6 compression, hexadecimal case, IPvFuture,
generic host ambiguity and standalone path selection. See [URI syntax](uri.md)
for scope, named rule overrides and the changed lexical CST interiors.

Representative valid inputs, microseconds per parse:

| Input and entry point | Before (`0ea7613`) | After |
| --- | ---: | ---: |
| `alice@example.com`, facade | 142.43 | 53.49 |
| `+31612345678`, facade | 30.73 | 14.72 |
| `https://example.com`, facade | 4805.26 | 3658.99 |
| `https://example.com`, frozen | 2961.66 | 1891.01 |
| `alice@example.com`, frozen | 56.35 | 57.23 |
| `+31612345678`, frozen | 15.85 | 15.31 |

Windows/PHP 8.2.10/PCRE 10.40. Each table entry is the median of three worker
medians. For each worker/input there are ten warmups and seven batches of ten
measured calls. Baseline and current workers alternate for each scenario; the
baseline autoloader reads copies of the eight changed implementation files from
`0ea7613`, with unchanged files shared. Timings include fresh UTF-8 sources, full
recognition/CST construction, acceptance assertions and result release. They
exclude PHP startup, class loading and initial grammar construction. The facade
rows include automatic preparation behavior; frozen rows reuse an explicit
snapshot. Email/phone fixtures are intentionally limited application grammars,
not comprehensive protocol validation.

The first non-alternating run was visibly affected by host variation, so it was
not used for this table. Even the alternating frozen-email worker medians ranged
56.86–111.23 microseconds after the changes, compared with 55.56–58.14 before.
Treat small differences as noise, not latency guarantees. URI improvements do
not demonstrate transparent caching of mutable references: those remain fresh.
The before URI grammar rejected the intended-valid IPv6 fixture; that input is
reported as an acceptance mismatch, not included as successful validation.

Raw alternating-worker reports
include the fixture driver, source-copy manifest, ranges and reproduction steps.
Run `php docs/examples/uri.php` for a quick acceptance/source-round-trip check.

### Sparse execution state and session subjects (2026-10-16)

Successful syntax remains an immutable CST. Mutable collection, value, validation
and effect state now lives in a lazily allocated `ExecutionState`. Once a parent
has built its CST and completed guards, it retains only execution children with
deferred state. Plain child execution records can be released while their syntax
nodes remain available. Selected-child and validation-child relationships stay
distinct; semantic errors, cached values and commit transitions are still local
to each recognition. Grammars with operations on every node still need per-node
state, so savings depend on the grammar.

General Regex and IntegerNumber share a session-owned full subject for sources up to
`ParseLimits::maxRegexSubjectBytes`, 8 MiB by default. A zero cap disables caching;
larger sources retain uncached reads. Literal windows do not materialize this
subject. Each full-subject attempt still charges its original logical work,
including cache hits. See [file input](file-input.md#regex-boundaries) for ownership
and memory limits.

Local Windows/PHP 8.2.10/PCRE 10.40 measurements:

| Workload | Before | After |
| --- | ---: | ---: |
| 64 KiB, one Text child per byte: retained used memory | 44.01 MiB | 19.00 MiB |
| Same fixture: median parse | 683.00 ms | 689.98 ms |
| 256 KiB UTF-8 file, general regex chunks: median parse | 1079.29 ms | 123.30 ms |

The retention probe uses two warmups and seven measured samples, and reports
incremental `memory_get_usage(false)` over a prepared source and grammar. These
are retained used bytes, not allocator peaks or OS RSS. It keeps the complete
ParseResult live; keeping only the CST uses 19.00 MiB both before and after. The
fixture has no per-node semantic/effect registrations. There is no demonstrated
parse-speed improvement for that character-node fixture.

The regex benchmark uses seven samples with no explicit warmup, excludes source
setup and result release, and includes CST construction. Its allocated process
peak remains 6 MiB at PHP's allocation granularity. Literal-window matching on the
same file measured 77.44 ms after these changes versus 69.55 ms in the earlier
run; the subject cache is a benefit for arbitrary full-subject matching, not a
replacement for bounded literal windows. Before/after runs are separate local
samples, not isolated attribution or latency guarantees.

Raw reports include ranges and the
retention probe source. Reproduce the standard fixture with:

```sh
php tools/benchmark.php --source=file-utf8 --case=regex --size=262144 --iterations=7 --mode=recognition
php tools/benchmark.php --source=memory --case=text --size=65536 --iterations=7 --mode=recognition
php docs/examples/file-input.php
```

### Inner-loop preparation and accounting (2026-10-16)

The runtime now batches literal work charges while preserving partial work counts
and exact byte offsets when a budget is exhausted. Case-sensitive literal failures
use a byte-prefix comparison that retreats to a complete UTF-8 unit. ASCII
case-insensitive successes have a bulk path; other case-insensitive matches retain
per-unit folding with cached literal units. Set membership, regex patterns/windows
and range bounds cache preparation separately for source mode and effective case
policy. Validation remains lazy at recognition time.

Checked delegation reuses valid immutable spans, unmapped semantic projections
are created on first value access, and Repeat shares its child list until trivia
requires a distinct concrete list. Custom-parser boundary checks, diagnostics,
tree shape, cached values and explicit commit behavior remain in place.

Windows/PHP 8.2.10/PCRE 10.40, two warmups and nine samples per case:

| Workload | Before (`06c7328`) | After |
| --- | ---: | ---: |
| 64 KiB exact literal, UTF-8 memory | 9.409 ms | 0.008 ms |
| 64 KiB literal, first-character failure | 0.017 ms | 0.011 ms |
| 64 KiB literal, last-character failure | 99.613 ms | 0.320 ms |
| 64 KiB ASCII literal, case-insensitive success | 138.033 ms | 0.035 ms |
| 64 KiB, 64-unit Text children | 17.278 ms | 6.844 ms |
| 64 KiB, 64-unit literal-window regex children | 18.676 ms | 8.272 ms |
| 1,024 units, 1,024-member Set, member last | 200.380 ms | 7.911 ms |

These combined changes were measured in separate runs on successive days, not
isolated per-optimization experiments. Earlier repeat-heavy timings varied
substantially, and tiny exact-match timings are especially sensitive to PHP string
sharing: that fixture gives the source and terminal the same backing string.
The literal/folding cases contain ASCII through a UTF-8 source; the Set contains
unique Unicode members. Cold preparation and source setup are outside timing.
Literal/repeat timings include result release; Set timings exclude it. No file
or non-ASCII folding speedup is inferred from this table.

Raw before/after data records ranges, runtime, timings and commands. The historical
sweep checked acceptance and late-literal failure positions, reported literal/repeat
and Set sweeps, and included an accounting-only microbenchmark. The specialized
benchmark has been removed. The accounting-only microbenchmark is not
a parsing speedup or an exhausted-budget equivalence test. Regression tests cover
partial budgets, Unicode mismatch boundaries, byte/text cache separation, policy
changes, invalid UTF-8, frozen graphs and retained trivia/effect order.

### Literal and callback allocation improvements (2026-10-15)

On Windows, PHP 8.2.10 and PCRE 10.40, seven samples per case produced the
following local medians. Source setup is excluded; recognition includes the CST.
The before measurements use revision `7c0f17b`; after measurements include the
literal preparation/comparison and empty callback registration optimizations.

| Workload | Before | After | Allocated peak before / after |
| --- | ---: | ---: | ---: |
| 2 KiB UTF-8 memory, one Text per byte | 19.91 ms | 15.34 ms | 10 / 4 MiB |
| 64 KiB UTF-8 memory, 64-byte Text chunks | 108.82 ms | 18.01 ms | 6 / 4 MiB |
| 2 KiB UTF-8 file, 64-byte Text chunks | 8.49 ms | 0.67 ms | 4 / 4 MiB |

These are combined changes, not isolated attribution of either optimization.
Text caches its immutable literal units separately for byte and UTF-8 sources.
Successful case-sensitive literals use one bounded byte comparison; mismatch and
case-insensitive recognition retain the unit path. Work charges and limit error
positions remain per unit. Empty local collection/effect registrations no longer
allocate closures, while registered callbacks still bind to each recognition.

The new `text-chunked` benchmark chooses runs of 1–64 `a` characters instead of
one node per character. At 64 KiB in UTF-8 memory, it took 9.58 ms and 4 MiB peak,
versus 557.96 ms and 62 MiB for the optimized character-node grammar. The latter
had a 536.48–2085.63 ms range, so its timing remains noisy. Before these changes,
the character-node case exhausted the default 128 MiB limit.

Coarser terminals are an explicit application grammar choice: they change CST
children and diagnostic expectations. The runtime never merges existing nodes.
Use them only when individual characters do not need names, guards, mappings or
effects. [The runnable example](https://github.com/vanderlee/Comprehend/blob/master/docs/examples/chunked-text.php) checks short and partial
final runs, malformed input, node counts and CST/AST source round trips:

```sh
php docs/examples/chunked-text.php
php tools/benchmark.php --case=text-chunked --mode=recognition --iterations=7 --size=65536
```

This example uses general regex over in-memory input. Its bounded match length
does not bound file subject reads; arbitrary regex still uses the complete source.
The acceptance language matches nonempty repeated `Text('a')`, but the CST shape
and mismatch expectations deliberately differ. Root-only AST and byte-length
semantic projections in the harness are inexpensive and do not model rich outputs.

Raw before/after reports retain
commands, ranges, peaks and source setup times. Samples share a worker process,
have no explicit warmup and include allocator retention and fixture storage.
These results are workload-specific baselines, not latency guarantees.

### Earlier file-window comparisons

| Case and source | Median parse + CST | PHP process peak |
| --- | ---: | ---: |
| 2 KiB text, in-memory, pre-change | 19.28 ms | 8 MiB |
| 2 KiB text, in-memory, phase 4 | 21.91 ms | 8 MiB |
| 64 KiB UTF-8 file, full-subject regex chunks | 63.47 ms | 6 MiB |
| 64 KiB UTF-8 file, bounded literal regex chunks | 20.88 ms | 6 MiB |
| 1 MiB UTF-8 file, bounded literal regex chunks | 406.75 ms | 52 MiB |
| 1 MiB UTF-8 memory, bounded literal regex chunks | 293.17 ms | 52 MiB |

The 64 KiB window comparison recognizes the same 64-byte literal chunks, with
the same node counts and output mode. It demonstrates reduced file rereads for
this workload; the full-subject version reads the entire file for every chunk.
There was no visible peak-memory difference at PHP's allocation granularity.
At 2 KiB in memory, windowed matching was slower (0.66 ms versus 0.37 ms), so it
is an explicit facility rather than a blanket replacement. Source setup for the
1 MiB file was 128.80 ms versus 5.74 ms in memory, excluded from parse time.
Budget bookkeeping and I/O have costs; no overall speedup is claimed.

Artifacts: pre-change baseline,
UTF-8 memory,
byte memory,
byte file,
UTF-8 file,
large text file,
large general regex,
large literal window,
megabyte file,
megabyte memory.

## Memoization decision

Memoization is **deferred**. The historical shared-prefix profile
recorded seven parser entries, one repeated parser/byte position and 4105 work
units. The lightweight `ParseMetrics` still omits scope context. That count is a
lead, not a cache benefit. The opt-in instrumentation below now separates weaker
policy repetitions from stricter context repetitions and measures their actual
logical work. No result cache, semantic-state replay or speculative effects are
introduced.

If pursued, keys must include frozen parser identity, source/session identity,
byte position, case/choice/trivia/lexeme policy and relevant guard dependencies.
Only pure guards and frozen graphs qualify, and selected execution records/value
caches/effects cannot be replayed as shared mutable cache entries. A bounded
cache with explicit file/history eviction and cached/uncached tests would be a
separate increment. Parser/byte-position counts alone do not justify those risks.

### Selective memoization instrumentation (2026-10-23)

`Runtime\MemoizationProfile` can observe existing frozen entry points:

```php
use Vanderlee\Comprehend\Runtime\MemoizationProfile;

$frozen = $grammar->freeze();
$profile = new MemoizationProfile(capacity: 4096, maxDepth: 128, maxContextBytes: 16384);
$result = $frozen->parseAll($source, memoizationProfile: $profile);
$counts = $profile->snapshot();
```

`FrozenGrammar::parse()` and `matchPrefix()` accept the same optional argument.
They register the already isolated, resolved and safety-checked graph, then use the
ordinary Parser/ParseSession recognition path. Object Parser entry points and
`ParseSession` can receive the observer too, but do not automatically qualify a
mutable graph: unregistered nodes supply entry/position counts, not reuse
candidates. Facades can freeze first. `ParseMetrics` remains the cheaper observer
with its existing snapshot schema. The default recognition path creates no
profile, context key or outcome summary.

Eligibility uses an exact-class allowlist of inspected built-ins and optimizer
implementations. Executable metadata, guards, callbacks, unknown/custom classes,
recursive dependency cycles and unsupported descendants disqualify a subtree.
Inspected children can still be eligible when their parent carries metadata.
Finite work/depth limits and active automatic spacing exclude reuse candidates;
they continue to be observed for total work and exceptions. Guard dependencies
are deliberately not guessed. Optimizer-internal recognizers outside the frozen
graph are unregistered, while bypassed lexical-table attempts generate no checked
entries. Neither absence nor repeated parser offsets establish cache usefulness.

Every position key includes monotonic weak identities for session, source and
parser, plus byte offset. Source identity also distinguishes its mode and stable
snapshot. Policy keys add effective case/choice, trivia identity, lexeme depth and
failure-diagnostic retention requirements. Strict keys additionally capture caller
rule paths, active ancestry identities/offsets, recursion membership, depth, full
policy stacks, active diagnostics and failure revision, pending diagnostic identity,
regex subject-cache presence and resource policy. Pending diagnostics are not
materialized. Actual caller identities prevent recycled PHP object IDs from
aliasing observations. Comparisons never cross sessions, even with the same Source.

`policy_repeats` and `strict_repeats` count completed visits to existing keys.
`*_summary_matches` further requires the same acceptance, byte progress, farthest
failure, root kind and immediate child count. These bounded summaries do not
compare complete trees or prove diagnostic-recipe equivalence. Policy summary
conflicts can be expected when caller diagnostics differ; strict conflicts would
invalidate that partition. Matching summaries still are not cache hits.
`*_repeated_work_inclusive` measures matching-summary repeated calls including
descendants. `*_repeated_work_nonoverlap` removes nested overlap; per-class
inclusive totals must not be added to estimate savings. Work units are cooperative
runtime charges, not CPU time, allocated bytes or guaranteed removable work.

Profiles retain bounded scalar keys, summaries and class totals, never a match,
tree, Source, effect or semantic projection. Weak identities and registered-node
records do not keep their objects alive. Each position/context table and graph
classification is capped by `capacity`; identity bookkeeping is capped at three
times that capacity. Frames are bounded per active session by `maxDepth` and are
removed on completion or exception. Context serialization is size-checked before
allocation; oversized policy stacks are not expanded. Bounds and all dropped
observation counters appear in the report. Saturation, conservative eligibility
and context-size truncation can hide candidates, so zero is not proof of absence.

The maintained harness uses fresh workers, 256-byte synthetic prefixes, eight
lookahead probes, ASCII application fixtures, byte Sources and separate cold/warm
observations. Three workers per case/flag confirmed these warm counts on Windows,
PHP 8.2.10, with PCOV disabled. All counts below are plain / optimized frozen graphs:

| Fixture | Entries | Repeated positions | Policy repeats | Strict repeats | Nonoverlapping strict repeated work |
|---|---:|---:|---:|---:|---:|
| Shared prefix, two alternatives | 7 / 6 | 1 / 0 | 1 / 0 | 0 / 0 | 0 / 0 |
| Eight repeated long lookaheads | 34 / 34 | 28 / 28 | 28 / 21 | 28 / 21 | 1,827 / 3,633 |
| Short lexical alternatives | 7 / 1 | 0 / 0 | 0 / 0 | 0 / 0 | 0 / 0 |
| Different case scopes | 9 / 9 | 1 / 0 | 0 / 0 | 0 / 0 | 0 / 0 |
| Guarded lookahead prefix | 34 / 34 | 28 / 28 | 7 / 7 | 7 / 7 | 14 / 14 |
| URI with repeated escapes | 1,179 / 49 | 15 / 0 | 15 / 0 | 0 / 0 | 0 / 0 |
| JSON array of objects | 651 / 603 | 18 / 18 | 18 / 18 | 0 / 0 | 0 / 0 |
| ABNF quoted text | 650 / 7 | 0 / 0 | 0 / 0 | 0 / 0 | 0 / 0 |

The plain lookahead's inclusive repeated work is 5,460, but its nonoverlapping
total is 1,827 of 2,347 observed work units. Optimized nested literal failure
fallback adds speculative comparisons: total work is 4,411 and nonoverlapping
repeated work is 3,633. Some internal compiled terminals are unregistered, so their
position repetitions are not eligible candidates themselves. The guarded variant
excludes the expensive guarded prefix and its parents; only seven cheap suffix
failures, two units each, qualify. This demonstrates why raw repetition counts
overstate selective opportunities.

Shared-prefix and ordinary URI policy keys also expose inherited-diagnostic summary
conflicts; their matching parser positions are not interchangeable execution
records. JSON's default 16 KiB context cap drops some observations. A separate
128 KiB run eliminates those context-size drops and still finds zero strict
repetitions in this sample. UTF-8 file workers exercise the same fixtures against
64-byte pages, and validate outputs against an independent unprofiled graph.
These are small samples with conservative keys: relative diagnostic/CST recipes
might allow broader reuse, but this profile does not prove that equivalence.

Instrumentation traverses graph eligibility, hashes scoped arrays and observes
outcomes; it is intentionally expensive. Its elapsed time is profiler overhead,
not evidence of a parsing optimization. The harness records collector/grammar-cache
used-memory deltas, recognition peaks before correctness projection, cold/warm
counts, and separate unprofiled recognition timings. Warm used-memory deltas in
the byte-source sample ranged from 6,232 bytes for optimized short lexical choice
to 1,567,000 bytes for ordinary URI recognition. These retain the collector and
any new grammar caches, not a hypothetical result cache. Registration adds graph
inspection only when a profile is supplied; context hashing is per observed
eligible visit. Bounded records avoid unbounded input-position history, but do not
make instrumentation free or establish a suitable production cache size.
A five-worker alternating
original/current comparison with profiling disabled measured ABNF worker medians
of 4.439–4.774 ms before and 4.305–5.010 ms after; medians of worker medians were
4.488 and 4.409 ms. Ranges overlap: no consistent default throughput improvement
or regression is established. The control loads Parser and ParseSession from
revision `4e8502f`; host activity was uncontrolled.

Reproduce:

```sh
php docs/examples/memoization-profile.php
php -d pcov.enabled=0 tools/profile-memoization.php --size=256 --workers=3
php -d pcov.enabled=0 tools/profile-memoization.php --source=file-utf8 --size=256
php -d pcov.enabled=0 tools/profile-memoization.php --case=json --context-bytes=131072
php -d pcov.enabled=0 tools/profile-memoization.php --no-profile --case=abnf --flags=0
```

Optional `--capacity`, `--case`, `--flags=0|1`, `--iterations` and
`--source=memory-byte|memory-utf8|file-utf8` control the experiment. To compare the
previous disabled path, save that revision's `src/Parser/Parser.php` and
`src/Runtime/ParseSession.php` under `build/memoization-profile/` and add
`--baseline-runtime=build/memoization-profile` to the `--no-profile` command.
Raw local reports are retained under that ignored directory while available.
Regressions compare profiled/plain CST/AST, values, ordered diagnostics, work,
guard/effect lifecycle, source release, deferred diagnostics and exact exhausted
limits. The runnable example checks a repeated lookahead at a nonzero byte offset.

The next justified cache experiment would target a bounded, expensive pure
lookahead with frequent context matches, comparing uninstrumented cached/uncached
workers. It must prove full relative diagnostics and tree reconstruction, fresh
semantic/effect ownership and exact work/limit behavior. Reject it if broader
context checks erase the hits, bounded recipes/lookup cost exceed recognition,
or memory and preparation outweigh reuse. Current URI/ABNF/JSON samples supply no
strict repeated-work evidence for a general cache; that is not a proof that all
relative-context memoization is unhelpful. No cache is enabled by this instrumentation.

## Lifetime and memory guarantees

- File source copying and validation use bounded chunks; random reads retain one
  configured page. The stable snapshot occupies O(input size) temporary disk.
- Requested byte slices allocate their requested size. General Regex and Integer
  allocate complete subjects, shared within the configured session cache cap;
  bounded literal regex reads a finite safe window.
- Every accepted parse retains its CST and deferred execution/semantic/effect
  records where needed. Plain execution children can be released after tree
  construction. Tree memory grows with matched nodes; the earlier 1 MiB example's 52 MiB peak
  demonstrates why this is not constant-memory streaming.
- ASTs, arrays, JSON/XML, raw text and semantic values add output-dependent
  retention. Diagnostics scan their prefix and current line lazily, retaining
  constant position metadata; a requested long line/prefix can still be large.
- Results and spans retain their source and snapshot lifetime. Explicit close
  invalidates later data reads. See [File input](file-input.md) for ownership.
- Runtime budgets count cooperative recognition work/depth, not wall-clock CPU,
  source construction or arbitrary semantic/effect callbacks.

Optional CST retention is deferred: ASTs, raw source reconstruction, serialization,
validation and semantic callbacks currently rely on that tree contract. A
recognition-only mode needs its own output/diagnostic contract before removing
nodes. The benchmark does not label the existing runtime as tree-free parsing.
