# Execution semantics

Comprehend executes interpreted parser combinators. It has no LR item sets,
parse tables, shift/reduce engine, memoization or general ambiguity resolution.
Recursive references require consuming progress; left recursion is unsupported.

Choice uses first success by default, or explicit longest/shortest preference.
A sequence never retries a previously successful choice after a later item fails.
For example `(firstOf('a','ab'), 'c')` fails on `abc`; selecting the longest choice
or factoring the shared prefix changes that grammar. Repetition is greedy and
does not shrink for a later item. Bounds must be nonnegative and ordered; zero
maximum does not invoke the child; nullable successful repetition throws.

All recognition uses Source, byte positions and ParseSession. UTF-8 code points
are consumed whole; byte input preserves arbitrary octets. Prefix/full-input
policy and leading/trailing trivia are explicit. Syntax selection is pure,
semantic output is cached, and effects need explicit commit. There is no old
string runtime or implicit automatic callback entry point.

The [execution examples](examples/execution.php) exercise these rules, Unicode,
octets, custom extensions, named grammars, AST/CST serialization and lifecycle.
See [sources](sources.md), [consumption](consumption.md) and [lifecycle](lifecycle.md).
