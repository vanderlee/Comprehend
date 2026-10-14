# Frequently asked questions

**Does Comprehend read grammar files?** A documented [ABNF subset](abnf.md)
compiles into the existing object graph. Arbitrary BNF/EBNF, generated PHP and
general ambiguity handling are unsupported.

**Does parsing execute callbacks?** Recognition does not execute effects.
Read semantic projections explicitly and call `commit()` for accepted effects.

**Are offsets characters?** No. Public offsets/spans are bytes; Utf8Source
provides explicit code-point conversions.

**Is old API compatibility supported?** No. See the
[breaking changes](canonical-runtime.md#breaking-changes).
