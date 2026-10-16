# TakeWhile experiment

This is a benchmark-only prototype, not a supported library API. Set
`COMPREHEND_TAKE_WHILE=1` to replace only the comparison grammar's
`set(" \t\r\n")->many()` spacing with `TakeWhile`. Without the variable,
the existing grammar remains active. Predicates receive raw source units
(UTF-8 code points or bytes) and must be pure. Scanning uses the normal
session, source boundaries and work budget.

PowerShell reproduction:

```powershell
$env:COMPREHEND_TAKE_WHILE = '1'
php tools/compare-binding.php --without-trees --samples=5 --iterations=30 --output=build/take-while-after.json
Remove-Item Env:COMPREHEND_TAKE_WHILE
php tools/compare-binding.php --without-trees --samples=5 --iterations=30 --output=build/take-while-before.json
```

On PHP 8.2.10 / Windows, 2026-10-16, two runs per variant in baseline,
prototype, prototype, baseline order produced these medians of ten worker
medians (30 parses per worker, two warmups, trees disabled):

| Case | Existing spacing (µs) | TakeWhile (µs) | Time reduction |
|---|---:|---:|---:|
| Record | 1199.5 | 838.0 | 30.1% |
| Default | 560.0 | 423.5 | 24.4% |
| Nested | 11992.0 | 8328.2 | 30.6% |
| Malformed field | 214.4 | 163.8 | 23.6% |
| Multiline error | 436.6 | 317.5 | 27.3% |
| Trailing input | 269.1 | 210.6 | 21.7% |

Every worker checked the complete 21-case acceptance/domain-value oracle.
The comparison tests passed with the prototype enabled and disabled. These
checks do not establish equivalent diagnostic expectations or CST shape:
the primitive replaces a repetition subtree with one terminal. Public API,
callback/type coverage and comprehensive source/lifecycle tests remain work
for a full implementation. These local timings are exploratory.
