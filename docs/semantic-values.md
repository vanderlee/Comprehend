# Cached semantic output

`mapValue(callable(ParseResult): mixed)` adds a lazy typed mapping. `value()`
caches scalars, null, arrays, objects and thrown errors for that recognition.
Without a mapping it returns raw text. Transparent runtime wrappers preserve
their accepted child's mapping unless explicitly mapped. Failed results return
null; abandoned alternatives do not evaluate their mappings automatically.

Facade `mapText(callable(string): mixed)` is the text-only convenience: its callback
receives the original matched string instead of ParseResult. It uses the same lazy
value cache, supports typed return values, and never changes raw source text or
commits effects. Both methods set the same mapper; a later call replaces the previous
mapping rather than transforming its output.

Named collections remain useful supported projections: `setResult(name, value)`,
`concatResult(name, value)` and `pushResult(name, value)`. A callable value maps
raw text. `results()` returns the named map; `result()` accesses the default
collection, or `result(name, default)` accesses a named value. A transform is
evaluated once per recognition even when both local semantic validation and a
parent collection read need it. Errors are retained and recursive evaluation is
rejected. A stored null is distinct from a missing key when using a default.
Collection operations do not affect raw text, CST or AST.

Definition processors receive the local result collection and produce a named
value. They replace local collection output, retaining child syntax, validation
and effects. Processor results/errors are cached per recognition; they do not
depend on mutable parent output. Definition validators receive ParseResult under
the selected [semantic lifecycle](lifecycle.md).

Later grammar mutations do not change accepted mappings, collection operations,
names, validators or effects. Builders themselves remain mutable until Phase 3;
each separate recognition has its own output caches.
