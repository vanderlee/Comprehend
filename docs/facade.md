# Immutable authoring facade

`Grammar` composes the same supported Parser types through immutable
`then()`, `or()`, `repeat()`, `named()`, `spacing()` and related operations.
`parser()` exports a detached graph; `freeze()` resolves and checks a reusable
snapshot. Reusing a facade expression does not mutate previously built grammars.

See [Grammar authoring](grammar-authoring.md) for signatures, cache/ownership
contracts and complete object/Ruleset/facade examples. Existing typed helpers in
`Vanderlee\Comprehend\Library` remain explicit object constructors; they do not
coerce strings/arrays into parsers.
