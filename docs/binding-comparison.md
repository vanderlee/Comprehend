# Application-value comparison with Binding

Comprehend's extraction grammar now explicitly installs its own
[Binding expansion](binding.md). The comparison label “Binding” below identifies
the separately pinned comparison dependency, not that expansion. Recorded reports
predate this packaging change and remain historical measurements.

This harness compares Comprehend's current checkout with Binding 0.8.3, using
complete input recognition and the same Job domain object. It checks acceptance
and field values against an independent oracle before accepting timings. It does
not measure equivalent internal work: Comprehend also constructs and retains its
concrete syntax tree. Binding's selected output is the domain value.

## Reproduce

Install the optional, pinned dependency separately from the library's development
dependencies. Its manifest and lockfile are under `tools/binding`; Composer places
the installation in ignored `build/binding/vendor`.

```sh
composer install --working-dir=tools/binding --no-interaction --prefer-dist --no-scripts
php tools/compare-binding.php --verify-only --output=build/binding-correctness.json
php tools/compare-binding.php --samples=3 --iterations=10 --warmups=2 --size=128 --output=build/binding-comparison.json
php tools/compare-binding.php --scanners --samples=8 --iterations=30 --warmups=3 --size=128 --output=build/binding-scanners.json
php tools/binding/type-report.php --output=build/binding-types.json
```

The harness does not install dependencies automatically. A missing dependency,
worker failure, acceptance mismatch or wrong domain output fails the command.
Use `--case=record` for a focused timing run; correctness checks still cover the
entire corpus. `--size` controls the number of IDs in the generated nested case.
The supplied regression test always checks Comprehend and skips only its Binding
case when the optional dependency has not been installed.

## Grammar and output contracts

The [two factories](https://github.com/vanderlee/Comprehend/blob/master/tools/binding/grammars.php)
recognize a name, an optional retry setting and nested lists:

```text
report retry=off [[1, 2], [], [3]]
report [[0,999]]
report retry=0 []
```

Names begin with an ASCII letter and continue with letters, digits or underscores.
Retry counts are single digits, or `off` for false; omission defaults to 3. IDs
are decimal 0 through 999 without leading zeros or signs. Lists may be empty;
trailing separators are rejected. Spaces, tabs, CR and LF are allowed between
tokens, but document boundary whitespace is rejected. This benchmark's unsigned
ID lexeme is explicit in both grammars; it is not a comparison of built-in integer
terminals. The corpus has 21 cases, including malformed optional fields, numeric
bounds, missing delimiters and trailing input. Passing a finite corpus does not
prove language equivalence for arbitrary input.

Both factories use field collection followed by a pure constructor mapping.
Comprehend supplies intervening spacing and value-preserving delimiters and lists.
Binding explicitly prefixes token parsers with spacing and uses its functional
choice/list/delimiter combinators. Its bounded ID recognition uses a text scan
followed by validation through `bind()`. These lexical implementation differences
are included in the measurements, not isolated from them. Source length alone is
not treated as an ergonomics score.

## Measurement boundaries

Each library/case/sample gets a fresh PHP worker. Starting positions rotate
between samples, with order reversed on alternate complete cycles. Workers load
the repository autoloader and shared domain class;
only Binding workers additionally load the optional dependency. Grammar creation
and Comprehend freezing are recorded separately as preparation time.
Current workers explicitly disable PCOV coverage instrumentation for both libraries.

After correctness checks and warmup, each timed call creates a fresh UTF-8
source/stream, recognizes complete input and obtains the selected domain value.
Assertions, diagnostic formatting, result release and cycle collection occur
outside the timer. The reported worker median uses the two middle observations
for an even iteration count. Raw reports retain individual timings and worker
minima/maxima. Failed cases measure recognition without a domain projection.

Memory records include warmed baseline used/allocated bytes, retained used-memory
delta with the last result and domain value alive, and process used/allocated
peaks across the measured loop. Cycles are collected between parses outside timing.
The retained delta is not allocator peak or OS RSS. Peak counters reset after
warmup on PHP 8.2+; on PHP 8.1 they cover the whole worker lifetime, as marked in
the report. CST node counts make Comprehend's retained syntax visible. Small
allocator differences, host noise and a single environment limit interpretation.
Corpus strings and expected values are allocated before the baseline, so retained
deltas exclude fixture storage; source objects can share those existing strings.

## Recorded run: 2026-10-16

Windows, PHP 8.2.10, native mbstring with UTF-8 internal encoding and PCRE 10.40.
Three workers per library/case, two warmups and ten timed parses per worker.
The nested fixture contains 128 generated IDs plus empty and singleton batches.
The following times are medians of worker medians, in microseconds:

| Case | Comprehend | Binding |
| --- | ---: | ---: |
| Record with optional setting and nested lists | 1,226.6 | 124.2 |
| Omitted setting/default | 592.6 | 74.1 |
| Larger nested list | 12,821.6 | 1,383.9 |
| Malformed optional field | 206.5 | 26.8 |
| Multiline malformed list | 449.4 | 59.3 |
| Trailing input | 273.2 | 39.0 |

For the record case, retained used-memory deltas were 71,912 bytes for Comprehend
(69 CST nodes) and 1,064 bytes for Binding. For the nested case, they were 600,792
bytes (822 CST nodes) and 3,464 bytes. These deltas were stable across the three
workers. Both libraries reported 4 MiB allocated process peaks in all measured
cases; allocator granularity obscures the difference visible in retained used bytes.

The [raw runtime report](benchmarks/binding-2026-10-16.json) contains all oracle
inputs, diagnostics, measurements, dependency reference, source hashes and the
reproduction command. These data demonstrate an extraction-cost gap on these
workloads; they do not establish a universal speed ratio or attribute the gap
solely to tree construction.

## Explicit scanner comparison, 2026-10-16

The `--scanners` run compares four variants against the installed Binding 0.8.3:

- `comprehend`: the existing regex tokens and `set(...)->many()` spacing.
- `comprehend-spacing`: only spacing changes to `readWhileSet()`.
- `comprehend-scanners`: spacing and name/digit recognition use `readWhileSet()`
  and `readWhileRange()`. Names and numbers retain their lexical constraints;
  composed token scanners use `lexeme()` to reject embedded whitespace.
- `binding`: the unchanged reference grammar using the actual pinned dependency.

All Comprehend variants construct CSTs on this branch; ASTs are not requested.
There is no `trees: false` mode in this run. The workload has no natural use for
`readUntil()`, `readRest()` or their skip variants, so their speed is not measured.

PHP 8.2.10 on Windows, native mbstring, PCOV disabled in all timed workers.
Each table entry is the median of eight worker medians, with 30 timed parses
and three warmups per worker. The 192 workers each checked all 21 corpus cases
before timing. Each variant occupied each execution position twice per case.

| Case | Existing Comprehend (µs) | Scanner spacing (µs) | Scanner tokens and spacing (µs) | Binding (µs) |
| --- | ---: | ---: | ---: | ---: |
| Record | 1177.8 | 852.9 | 1153.4 | 100.5 |
| Default | 538.7 | 438.0 | 559.1 | 57.9 |
| Nested list | 12231.3 | 8503.7 | 18365.0 | 1107.9 |
| Malformed field | 193.8 | 155.5 | 205.1 | 20.7 |
| Multiline error | 434.6 | 320.6 | 452.7 | 44.5 |
| Trailing input | 268.5 | 206.4 | 241.0 | 28.5 |

Changing only spacing reduced parse time by 18.7–30.5% across these cases.
Binding remained 7.6–8.5 times faster on successful cases and 7.2–7.5 times faster
on rejected cases. These are local observations, not universal ratios.

Replacing the regex tokens with composed scanners did not improve on scanner
spacing alone. In the nested case, time increased from 8503.7 to 18365.0 µs.
The regex already handles a complete number in one terminal invocation; the
scanner version needs a choice and multiple bounded terminals to retain the
leading-zero and digit-count restrictions. Its retained CST contains 1336 nodes
instead of 820 for scanner spacing alone. This measures those grammar constructions,
not an inherent slowdown of scanner primitives relative to every possible regex.

For the record, retained used bytes were 56848 (existing), 54808 (scanner spacing),
63200 (scanner tokens and spacing), and 1064 (Binding). For the nested list these
were 398144, 397328, 729784, and 3464 respectively. Whitespace scanners reduce
recognition overhead substantially while most retained syntax remains present.

The [raw scanner report](benchmarks/binding-scanners-2026-10-16.json) records
all timings, correctness observations, source hashes and the command. Current
regression tests also cover six additional lexical cases, including whitespace
inside names/numbers, invalid name prefixes and leading zeros. Diagnostic output
and CST structure may differ between variants; only acceptance, domain output and
source round trips are required to agree. Coverage settings, branch contents,
and grammar changes mean these timings should not be treated as a controlled
before/after comparison with historical reports above.

## Diagnostics

Every rejected corpus case provides a message and one-based line/column in both
libraries. The report preserves native diagnostics rather than requiring exact
message or coordinate equality. Comprehend also provides its byte offset.

For `report\n[[1,\n?]]`, both report line 2, column 2, rather than the malformed
item on line 3. List-extension rollback contributes to this earlier diagnostic;
neither result should be described as pinpointing the invalid item. For the tab
fixture, Comprehend reports column 9 and Binding column 10. For the CRLF fixture,
Comprehend reports line 2 and Binding line 3. These examples exercise different
tab and newline conventions. A consumer must not compare those coordinates as
though they used one common convention.

These observations describe the recorded run. The current Comprehend runtime
retains abandoned list failures separately and uses them when an enclosing parse
fails. The multiline fixture now reports line 3, column 1 (byte 12). The original
report remains historical evidence; acceptance, values and prefix consumption
are unchanged. See [structured diagnostics](diagnostics.md).

## Static analysis observations

The optional type probe uses PHPStan 2.3.1 at level 10, with Comprehend's bundled
extension. It records diagnostics without suppressions. Its wrapper exits zero
when a complete report is captured; that does not mean both grammars pass static
analysis. The underlying analyzer exits 1 because `dumpType()` deliberately
produces diagnostics and other reported errors remain present.

| Expression | Comprehend | Binding |
| --- | --- | --- |
| Mapped digit parser | Parser of int | Parser of int |
| Repeated digit values | Parser of list of int | Parser of list of int |
| Optional digit with false default | Parser of int or false | Parser of int or bool |
| Domain output after parsing | Job or null | Job |

The domain factories have explicit generic return contracts; those rows are not
claims of unannotated factory-return inference. The Binding output method's type
does not imply it is safe on failure: the runtime wrapper checks success first.
The eight remaining argument-type diagnostics in the Binding grammar involve
mixed combinator inputs, field construction and callback purity under this
analyzer. Comprehend's factory passes the same probe. See the
[raw type observations](benchmarks/binding-types-2026-10-16.json). Binding's native
analyzer workflow was not run, so this is not a comparison with its usual tooling.

## Implications

The current APIs can express this extraction task in both libraries. Comprehend's
parse/value path and retained syntax costs are covered by the
[extraction profile](extraction-profiling.md). The multiline list diagnostic has
subsequently been improved as described above.
The comparison does not justify adding more authoring helpers or changing the
runtime's tree, lazy-value or effect contracts without separate evidence.
