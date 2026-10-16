# Diagnostic allocation and validation-state retention

This compares the working tree before and after diagnostic snapshot caching and
validation-path pruning. The baseline source commit is
`5d4872c6e07b580bd7fe83a94aa2ec749ed5c861`.

Unchanged failure details now share a Diagnostic object within their parse
session. Updates and rollback invalidate the active cache; deferred-list merges
are cached separately. Checkpoint structure, expected-label ordering, paths,
byte positions and retained diagnostic snapshots are preserved.

After CST construction, the concrete validation graph retains only validators,
recoveries and descendant validation state. Value-only children still retain
their separate semantic, collection and commit dependencies. In particular,
commit status observable through a selected child ParseResult is preserved.
Custom execution records conservatively retain validation traversal. Validation
without local validators no longer constructs an unnecessary ParseResult wrapper.

## Same comparison against Binding

Both captures use the actual installed Binding 0.8.3 at reference
`e5b0a763e26e89de39a0790e949b8ff7f0909d73`, with PHP 8.2.10, Windows and native
mbstring. PCOV is disabled in timed workers. CST is enabled; AST is not requested.

The corpus, grammar and timing boundaries are unchanged: fresh source, complete
recognition and Job projection, excluding preparation, assertions, diagnostic
formatting, result release and GC. Four grammar variants are retained in each
raw report. Each has eight fresh workers per case, 30 parses and three warmups,
with rotated/reversed order. Each of the 192 workers per capture checks all 21
oracle cases first. The before/after acceptance and native diagnostic observations
are identical. The harness was formatted between captures; its measurement logic
and settings are unchanged.

These figures use the recommended `comprehend-spacing` variant: `readWhileSet()`
spacing with the original whole-token regexes and `collect()` projection.

| Case | Before (µs) | After (µs) | Binding after (µs) |
| --- | ---: | ---: | ---: |
| Record | 859.8 | 842.1 | 103.7 |
| Default | 434.0 | 435.0 | 58.6 |
| Nested list | 8644.4 | 8644.4 | 1132.8 |
| Malformed field | 158.7 | 162.5 | 21.6 |
| Multiline error | 324.7 | 320.9 | 45.6 |
| Trailing input | 209.9 | 211.1 | 28.3 |

The small timing changes are mixed; no substantial parse/value throughput gain
is established. Binding remains about 7.4–8.1 times faster on successful cases.
The benchmark does not call semantic validation or commit, so it does not measure
the reduced traversal cost in those phases.

| Retained result memory | Before (bytes) | After (bytes) | Reduction |
| --- | ---: | ---: | ---: |
| Record | 54808 | 50680 | 7.5% |
| Default | 26728 | 25432 | 4.8% |
| Nested list | 397328 | 392336 | 1.3% |

CST node counts are unchanged. The separate method trace for the profiler's
ordinary-spacing, unfrozen record grammar shows Diagnostic construction falling
from 101 to 51 objects and Span construction from 316 to 266 per recognition.
ExecutionState construction remains 42: temporary recognition/CST assembly still
needs those records. Trace CST hashes and work-unit counts agree before/after.
Instrumented timings are not used as throughput measurements.

## Reproduce and evidence

The external framework remains installed at its previous build path; the explicit
autoloader option checks both the pinned version and reference.

```sh
php tools/compare-extraction.php --scanners --samples=8 --iterations=30 --warmups=3 --size=128 --reference-autoload=build/binding/vendor/autoload.php --output=build/lifecycle-comparison.json
php -d pcov.enabled=0 tools/profile-extraction.php --trace --case=record --iterations=3 --output=build/lifecycle-profile.json
```

- [Baseline timings and diagnostics](diagnostic-lifecycle-before-2026-10-16.json)
- [Updated timings and diagnostics](diagnostic-lifecycle-after-2026-10-16.json)
- [Baseline method trace](diagnostic-lifecycle-profile-before-2026-10-16.json)
- [Updated method trace](diagnostic-lifecycle-profile-after-2026-10-16.json)

Regression tests cover cache reuse, stable old snapshots, rollback with colliding
revision counters, deferred-list merging, release of concrete-only value records,
observable child commit state and custom validation. Runtime correctness and
canonical API checks pass. Full static analysis retains the five previously
reported errors in facade return types, the tutorial and type fixtures; the two
type-inference suite failures correspond to those existing errors. Changed
runtime files pass scoped PHPStan analysis. Coverage collection is disabled.
