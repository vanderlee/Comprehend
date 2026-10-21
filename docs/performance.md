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
  retention. Diagnostic line starts are scanned lazily in bounded chunks, but
  the index grows with line count; a requested long line/prefix can be large.
- Results and spans retain their source and snapshot lifetime. Explicit close
  invalidates later data reads. See [File input](file-input.md) for ownership.
- Runtime budgets count cooperative recognition work/depth, not wall-clock CPU,
  source construction or arbitrary semantic/effect callbacks.

Optional CST retention is deferred: ASTs, raw source reconstruction, serialization,
validation and semantic callbacks currently rely on that tree contract. A
recognition-only mode needs its own output/diagnostic contract before removing
nodes. The benchmark does not label the existing runtime as tree-free parsing.
