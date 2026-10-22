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

Memoization is **deferred**. The shared-prefix profile
recorded seven parser entries, one repeated parser/byte position and 4105 work
units. It identifies a synthetic optimization candidate, not a demonstrated
cache benefit. Scope context is omitted from this bounded profile; repeated
positions are not proven interchangeable. No memoized/uncached equivalence or
measured cache benefit has been established, so a result cache is not enabled.

If pursued, keys must include frozen parser identity, source/session identity,
byte position, case/choice/trivia/lexeme policy and relevant guard dependencies.
Only pure guards and frozen graphs qualify, and selected execution records/value
caches/effects cannot be replayed as shared mutable cache entries. A bounded
cache with explicit file/history eviction and cached/uncached tests would be a
separate increment. Parser/byte-position counts alone do not justify those risks.

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
