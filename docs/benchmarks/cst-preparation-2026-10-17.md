# CST preparation: fewer validation calls

The scanner-spacing nested profile constructs 826 ConcreteNode objects. Previously
each constructor called validateStructure and then validateChildren, for 1652
helper calls. Plain leaves with empty children and tags now avoid those helpers;
their empty arrays already satisfy both list and element invariants. Nonempty
nodes use one validator containing the same tag and child checks in the same
order. The profile now records 284 validation helper calls, 82.8% fewer.

This retains every node, the checked recognition path, dynamic naming, guards,
source identity checks, nonempty tag/list validation, and lifecycle handling.
No trusted-node factory or alternate runtime is introduced. A broader prototype
inlined all validation into the constructor, but conflicted with PHPStan's typed
constructor contracts. The retained version keeps untrusted-array validation
separate without widening those contracts or suppressing errors.

Median of eight worker medians, microseconds per complete parse and value read:

| Case, readWhileSet spacing | Before | After | Reduction |
| --- | ---: | ---: | ---: |
| Record | 776.18 | 788.18 | -1.5% |
| Default | 412.80 | 395.45 | 4.2% |
| Nested | 7958.20 | 7589.78 | 4.6% |
| Malformed field | 141.78 | 143.85 | -1.5% |
| Multiline error | 288.75 | 286.35 | 0.8% |
| Trailing input | 195.88 | 196.60 | -0.4% |

Binding's record/default/nested timings vary from 104.70/59.55/1126.23 to
103.78/58.23/1151.15 microseconds. These are sequential local measurements with
mixed small changes; no general throughput speedup is established. The retained
change removes repetitive helper calls with a small implementation diff.

Before/after profile allocation counts, CST hashes and work counts are identical.
Every comparison worker verifies the full 21-case oracle; its complete reported
correctness observations are identical. Timings use the actual pinned Binding
0.8.3 dependency and disable PCOV. Preparation, diagnostic formatting, release
and garbage collection are excluded. Instrumented profile timings are not used
as throughput evidence.

```sh
php -d pcov.enabled=0 tools/profile-extraction.php --trace --scan-spacing --case=nested --iterations=1 --output=build/success-before-profile.json
php -d pcov.enabled=0 tools/compare-extraction.php --scanners --samples=8 --iterations=30 --warmups=3 --size=128 --reference-autoload=build/binding/vendor/autoload.php --output=build/success-before.json
```

Run the same commands after the change with distinct output names. Raw reports:
[before](success-before-2026-10-17.json),
[after](success-after-2026-10-17.json),
[profile before](success-before-profile-2026-10-17.json),
[profile after](success-after-profile-2026-10-17.json).

Verification: 4455 tests pass, with the same two existing type-inference failures,
one skip, nine deprecations and two warnings. Three new leaf-node regression
cases cover malformed tags; existing tree tests cover foreign-source children,
invalid child lists, overlapping children and empty roots. Full PHPStan retains
its five existing errors. Composer validation/platform requirements, canonical
gate, scoped formatting checks and execution/record examples pass.
