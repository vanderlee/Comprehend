# Byte-offset checks and span construction

Diagnostic construction, failure recording and match metadata previously allocated
zero-width spans solely to validate a byte offset. They now use a direct byte-range
check. Match metadata skips that check when an immutable Diagnostic already
validated the same source and offset. Foreign-source or different-offset diagnostics
cannot bypass validation. Recognition boundary checks remain unchanged.

Span::cover also reuses either operand when it already covers the other. This
preserves source identity, coordinates and immutability; it is not a span pool.

The ordinary-spacing record profile drops from **266 to 97 Span allocations**
(63.5% fewer). Parser entries remain 100, work units 319, and the complete CST
hash is unchanged. All other profiled source allocation counts are unchanged.
The full 21-case benchmark oracle reports identical observations.

Microseconds per complete parse and value read, median of eight worker medians:

| Case, readWhileSet spacing | Baseline | Optimized | Baseline repeated |
| --- | ---: | ---: | ---: |
| Record | 827.05 | 795.65 | 816.38 |
| Default | 434.43 | 404.30 | 414.83 |
| Nested | 8750.28 | 7583.38 | 8375.45 |
| Malformed field | 152.45 | 140.88 | 148.58 |
| Multiline error | 319.35 | 293.35 | 316.73 |
| Trailing input | 217.55 | 196.70 | 199.68 |

These local sequential runs show timing variability. Against the repeated
baseline, the successful cases improve by 2.5%, 2.5% and 9.5%, respectively.
Binding's corresponding timings also shift: 105.58 to 100.00, 58.55 to 59.58,
and 1163.78 to 1115.20 microseconds. Small timing differences should not be
treated as established speedups. The allocation reduction is the clearest result.
Comprehend continues to build its full CST; the actual pinned Binding 0.8.3
dependency is used as reference.

An earlier exploratory version removed temporary spans but still rechecked all
match failure positions; its timings did not improve relative to Binding.
The reported optimized run includes reuse of validated diagnostic positions.
No UTF-8 recognition checks were removed and no global cache was introduced.

Commands, with before/after output names substituted:

```sh
php -d pcov.enabled=0 tools/profile-extraction.php --trace --case=record --iterations=3 --output=build/boundary-before-profile.json
php -d pcov.enabled=0 tools/compare-extraction.php --scanners --samples=8 --iterations=30 --warmups=3 --size=128 --reference-autoload=build/binding/vendor/autoload.php --output=build/boundary-before.json
```

Profile tracing counts source new-expressions; instrumented timings are not used
for throughput claims. Comparison workers disable PCOV and exclude preparation,
diagnostic formatting, release and garbage collection from the timing interval.
Raw evidence: [baseline](boundary-before-2026-10-17.json),
[optimized](boundary-after-2026-10-17.json),
[repeated baseline](boundary-baseline-repeat-2026-10-17.json),
[profile before](boundary-before-profile-2026-10-17.json),
[profile after](boundary-after-profile-2026-10-17.json).

The final implementation differs from the measured optimized source only in
multiline condition formatting. Full verification reports 4449 passing tests,
the same two existing type-inference failures, one skip, nine deprecations and
two warnings. PHPStan retains its five existing errors, with none in the changed
code. Composer validation/platform requirements, the canonical gate, affected
execution/file-input examples and the formatter check pass. Scoped PHPCS reports
no errors and one existing mixed-symbol/side-effect warning in SpanTest.php.
