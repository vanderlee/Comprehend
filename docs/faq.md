# Frequently asked questions

**Does Comprehend read BNF files?** No. Grammars are currently object/Ruleset
compositions. A frontend is optional later work.

**Does parsing execute callbacks?** Recognition does not execute effects.
Read semantic projections explicitly and call `commit()` for accepted effects.

**Are offsets characters?** No. Public offsets/spans are bytes; Utf8Source
provides explicit code-point conversions.

**Is old API compatibility supported?** No. See the
[breaking changes](canonical-runtime.md#breaking-changes).
