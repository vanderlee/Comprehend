# Successful-parse failure-path investigation

The earlier 25-Failure/51-Diagnostic record profile used the original whitespace
grammar. It overstates this cost for the faster scanner-spacing comparison.
Fresh attribution traces of successful recognition show:

| Case and whitespace policy | Failure | Diagnostic | Success | ConcreteNode |
| --- | ---: | ---: | ---: | ---: |
| Record, set plus repetition | 25 | 51 | 75 | 75 |
| Record, readWhileSet | 5 | 9 | 70 | 70 |
| Nested 128, readWhileSet | 4 | 4 | 826 | 826 |

These are source new-expression counts, not retained-object counts, allocated
bytes or CPU percentages. Attribution adds instrumentation overhead; no
throughput improvement is claimed and production parsing is unchanged.

## Where the allocations originate

In the original record grammar, 20 of the 25 Failures come from Set failing at
the end of whitespace repetition. The remaining five are one numeric Range,
three Text separator probes, and one Regex item probe.

The Range failure is a rejected numeric retry alternative before `off` succeeds.
The separator and item failures terminate lists normally, including an empty
inner list. Those probes are necessary under the current combinator semantics.
Their diagnostic information also matters for malformed lists and enclosing
failures; it cannot simply be suppressed whenever a child is optional.

Of the original 51 Diagnostic allocations, 47 are active snapshots and four
come from deferred-list diagnostic merges. The same byte-13 numeric-alternative
snapshot is recreated 23 times: later speculative whitespace/list probes replace
the session's active snapshot, and rollback restores its fields while clearing
the snapshot cache. Scanner spacing eliminates whitespace termination failures;
that byte-13 diagnostic is then constructed five times, accounting for four of
the nine snapshots beyond the five distinct failure observations.

The nested scanner-spacing case has no rejected retry alternative. Its four
diagnostics correspond to four terminal failures, despite 826 Success and CST
node allocations. Failure-path allocation reduction is therefore unlikely to
close much of the remaining gap on this case. This is an allocation-based
priority judgment, not a measured upper bound on CPU savings.

## Recommendation

Do not introduce general lazy diagnostics yet. Public results and internal
execution records currently expose concrete diagnostic snapshots, and custom
recognizers can observe them during recognition. Deferring them broadly would
need careful snapshot, rollback and observation-boundary changes.

If pursuing a small diagnostic experiment, first preserve a previously cached
active snapshot across diagnostic checkpoints and rollback. Keep checkpoint
compatibility, expected-label/path order, retained results and deferred-list
merges intact. This targets the four duplicate snapshots in the scanner-spacing
record, but promises little for nested scanner input. It has not been implemented
or benchmarked here.

For the faster grammar, investigate per-invocation successful-match preparation
and CST construction next. They scale with input items; these few failures do not.

## Reproduction and checks

```sh
php -d pcov.enabled=0 tools/profile-extraction.php --trace --failure-details --case=record --iterations=1 --output=build/failure-record.json
php -d pcov.enabled=0 tools/profile-extraction.php --trace --failure-details --scan-spacing --case=record --iterations=1 --output=build/failure-record-scanning.json
php -d pcov.enabled=0 tools/profile-extraction.php --trace --failure-details --scan-spacing --case=nested --size=128 --iterations=1 --output=build/failure-nested-scanning.json
```

Raw reports: [original record](failure-record-2026-10-17.json),
[scanner-spacing record](failure-record-scanning-2026-10-17.json),
[scanner-spacing nested](failure-nested-scanning-2026-10-17.json).
All verify expected domain values and full consumption. Detailed Failure and
Diagnostic counts are checked against aggregate allocation totals. Traced and
untraced scanner-spacing record tree hashes match. No source/runtime files or
installed dependencies changed. PHPStan retains the same five existing errors;
the canonical gate and scoped formatting checks pass.
