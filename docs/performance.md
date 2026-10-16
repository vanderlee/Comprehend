# Performance measurements and retention guarantees

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

[Raw alternating-worker reports](benchmarks/small-facades-uri-2026-10-16.json)
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

General Regex and Integer share a session-owned full subject for sources up to
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

[Raw reports](benchmarks/sparse-subjects-2026-10-16.json) include ranges and the
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

[Raw before/after data](benchmarks/inner-loops-2026-10-16.json) records ranges,
runtime, timings and commands. Run the maintained sweep with:

```sh
php tools/benchmark-inner-loops.php
```

It checks acceptance and late-literal failure positions, reports literal/repeat
and Set sweeps, and includes an accounting-only microbenchmark. The latter is not
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

[Raw before/after reports](benchmarks/literal-callback-2026-10-15.json) retain
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

Artifacts: [pre-change baseline](benchmarks/phase4-baseline.json),
[UTF-8 memory](benchmarks/phase4-memory.json),
[byte memory](benchmarks/phase4-memory-byte.json),
[byte file](benchmarks/phase4-byte-file.json),
[UTF-8 file](benchmarks/phase4-utf8-file.json),
[large text file](benchmarks/phase4-large-file.json),
[large general regex](benchmarks/phase4-large-regex.json),
[large literal window](benchmarks/phase4-large-window.json),
[megabyte file](benchmarks/phase4-megabyte-window.json),
[megabyte memory](benchmarks/phase4-megabyte-memory.json).

## Memoization decision

Memoization is **deferred**. The [shared-prefix profile](benchmarks/phase4-profile.json)
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
