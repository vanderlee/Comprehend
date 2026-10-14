# Immutable authoring facade

`Comprehend` composes the same supported Parser types through immutable
`then()`, `or()`, `repeat()`, `named()`, `spacing()` and related operations.
`parser()` exports a detached graph; `freeze()` resolves and checks a reusable
snapshot. Reusing a facade expression does not mutate previously built grammars.
Ruleset constructor entries, `define()` and `root()` accept facade values
directly and export detached parsers internally when registering them.

See [Grammar authoring](grammar-authoring.md) for signatures, cache/ownership
contracts and complete object/Ruleset/facade examples. Use `Comprehend::text()`,
`char()`, `set()`, `range()`, `regex()`, `sequence()` and `choice()` for authoring.
The former free functions in `Vanderlee\Comprehend\Library` have been removed;
use fluent `repeat()` for their repetition operations.
