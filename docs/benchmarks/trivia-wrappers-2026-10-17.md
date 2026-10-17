# Session-owned automatic trivia wrappers

Spacing previously called Parser::trivia() on every attempt. ParseSession now
lazily caches those wrappers by parser identity in a session-owned WeakMap.
Nested scopes can share a wrapper for the same policy, while different policies
remain distinct. Lexeme-suppressed spacing does not initialize the cache.
The grammar graph and snapshot implementation are unchanged. Recognition still
uses the same checked delegation path and creates fresh match records.

The ordinary-spacing record profile changes Trivia allocations from **20 to 1**,
with one new WeakMap. All other source new-expression counts are unchanged:
97 Span, 75 Success, 75 ConcreteNode, 51 Diagnostic, and 17 ExecutionState.
Work remains 319 units, parser entries remain 100, and the complete CST hash is
identical. Parser-position metrics can now recognize repeated wrapper visits
because the wrapper has a stable identity within the session.

Median of eight worker medians, microseconds per complete parse and value read:

| Case, readWhileSet spacing | Before | After | Reduction |
| --- | ---: | ---: | ---: |
| Record | 811.10 | 799.58 | 1.4% |
| Default | 416.95 | 398.10 | 4.5% |
| Nested | 8183.88 | 7566.00 | 7.5% |
| Malformed field | 142.43 | 144.75 | -1.6% |
| Multiline error | 305.90 | 300.18 | 1.9% |
| Trailing input | 196.43 | 199.80 | -1.7% |

Ordinary-spacing successful cases improve by 0.9%, 1.8% and 6.2%, respectively.
The actual pinned Binding 0.8.3 successful cases vary by -0.7% to 1.7%.
These are sequential local measurements; small changes may be noise. The clearest
finding is that repeated wrapper allocation is removed, with the largest measured
throughput benefit on nested input. Comprehend still constructs the full CST.
Every worker verifies the complete 21-case oracle before timing; the before/after
correctness observations are identical.

Commands, with before/after output names substituted:

```sh
php -d pcov.enabled=0 tools/profile-extraction.php --trace --case=record --iterations=3 --output=build/trivia-before-profile.json
php -d pcov.enabled=0 tools/compare-extraction.php --scanners --samples=8 --iterations=30 --warmups=3 --size=128 --reference-autoload=build/binding/vendor/autoload.php --output=build/trivia-before.json
```

Workers disable PCOV. Preparation, diagnostic formatting, release and garbage
collection are excluded from timings. Instrumented profiling timings are not
throughput evidence. Raw reports: [before](trivia-before-2026-10-17.json),
[after](trivia-after-2026-10-17.json),
[profile before](trivia-before-profile-2026-10-17.json),
[profile after](trivia-after-profile-2026-10-17.json).

Verification: the full suite reports 4451 passing tests and the same two existing
type-inference failures, plus one skip, nine deprecations and two warnings.
The subsequently expanded ParseSession suite passes all 18 tests (100 assertions),
including separate policy wrappers, nested restoration, session isolation, lazy
creation and independent commit state. Full PHPStan retains the same five existing
errors. Composer validation/platform requirements, canonical gate, scoped PHPCS
and formatter checks, and execution/record examples pass.
