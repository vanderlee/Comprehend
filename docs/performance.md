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
  allocate complete subjects; bounded literal regex reads a finite safe window.
- Every accepted parse retains its CST and selected execution/semantic/effect
  records. Tree memory grows with matched nodes; the 1 MiB example's 52 MiB peak
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
