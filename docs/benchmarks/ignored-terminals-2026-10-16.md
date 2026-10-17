# Plain-child wrapper experiment

Avoid allocating ExecutionState when a Success wraps one plain built-in child
without deferred state. Keep that child separately until CST construction, then
release it. Stateful and custom children use the existing path. This targets
trivia and ignored punctuation wrappers without changing the parser graph,
checked recognition path, CST, diagnostics, or public API.

The ordinary-spacing record profile has 100 parser entries and 319 work units.
ExecutionState allocations fall from 42 to 17. Success (75), ConcreteNode (75),
Span (266), and Diagnostic (51) counts stay unchanged. The complete CST hash is
identical. Instrumented timings are not used as throughput evidence.

Median of eight worker medians, microseconds per complete parse and value read:

| Case, readWhileSet spacing | Before | After | Reduction | Binding after |
| --- | ---: | ---: | ---: | ---: |
| Record | 884.35 | 797.85 | 9.8% | 101.50 |
| Default | 426.60 | 405.05 | 5.1% | 58.58 |
| Nested | 8899.00 | 7861.50 | 11.7% | 1126.80 |
| Malformed field | 156.13 | 148.35 | 5.0% | 21.08 |
| Multiline error | 322.80 | 298.35 | 7.6% | 44.03 |
| Trailing input | 209.43 | 203.33 | 2.9% | 28.43 |

Ordinary-spacing successful cases improve by 4.3–6.5%; the all-scanner successful
cases improve by 3.5–6.3%. Binding successful cases vary by at most 1.4% between
runs. These are sequential local before/after measurements, not a randomized
cross-version trial. The result supports retaining this small optimization, but
does not establish a universal speedup. Comprehend still builds the complete CST
and remains about 7–8 times slower on these successful scanner-spacing cases.

Both versions verify the complete 21-case oracle in every worker. Their reported
correctness observations are identical. The reference is the actual installed,
pinned Binding 0.8.3 dependency; PCOV is disabled. Preparation, diagnostic
formatting, release, and garbage collection are outside the timing interval.

Commands, run before and after the prototype:

```sh
php -d pcov.enabled=0 tools/profile-extraction.php --trace --case=record --iterations=3 --output=build/ignored-before-profile.json
php -d pcov.enabled=0 tools/compare-extraction.php --scanners --samples=8 --iterations=30 --warmups=3 --size=128 --reference-autoload=build/binding/vendor/autoload.php --output=build/ignored-before.json
```

Raw evidence: [before](ignored-before-2026-10-16.json),
[after](ignored-after-2026-10-16.json),
[profile before](ignored-before-profile-2026-10-16.json),
[profile after](ignored-after-profile-2026-10-16.json).

Verification: the full runtime suite reports 4441 passed and the same two
pre-existing PHPStan inference failures (plus one skip, nine deprecations and two
warnings). The two subsequently added wrapper regression tests pass, bringing
the focused Success suite to 11 passing tests. Full PHPStan retains the same five
existing errors; no errors are reported in the changed implementation. Composer
validation/platform requirements, canonical gate, scoped formatting checks, and
execution/record examples pass.
