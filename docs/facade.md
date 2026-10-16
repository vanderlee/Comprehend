# Immutable authoring facade

`Comprehend` composes the same supported Parser types through immutable
`then()`, `or()`, `repeat()`, `named()`, `spacing()` and related operations.
It is the public parser authoring API: Ruleset definitions, roots and factories,
plus Definition generators and `build()`, accept or return facade values without
manual exports. Low-level Parser subclasses remain the custom recognizer contract.
`parser()` exports a detached graph for low-level integration; `freeze()` resolves and checks a reusable
snapshot. Reusing a facade expression does not mutate previously built grammars.
Ruleset constructor entries, `define()` and `root()` accept facade values
directly and export detached parsers internally when registering them.
Both facade and frozen grammars expose `parse()`, `parseAll()` and `matchPrefix()`.
Facade parsing freezes current definitions per call; frozen parsing reuses its
fixed graph. Parsing and AST navigation never commit application effects.
Shared factory/fluent names use authoring-only magic dispatch. Extend syntax in
an immutable scope with `Comprehend::syntax()->defineRule(name, factory)`; factories
still produce the same graph, and unknown names raise errors. Ruleset lookup
remains explicit. See [Grammar authoring](grammar-authoring.md#scoped-expandable-syntax).

See [Grammar authoring](grammar-authoring.md) for signatures, cache/ownership
contracts and complete object/Ruleset/facade examples. Use `Comprehend::text()`,
`char()`, `set()`, `range()`, `regex()`, `sequence()` and `choice()` for authoring.
The former free functions in `Vanderlee\Comprehend\Library` have been removed;
use fluent `repeat()` for their repetition operations.
