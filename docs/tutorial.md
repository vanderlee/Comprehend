# Tutorial

Start with the [README example](../README.md#quick-start). Construct explicit
terminal objects, combine them in Sequence/Choice/Repeat, and pass Utf8Source or
ByteSource to `parseAll()` (complete input) or `matchPrefix()` (prefix input).
Inspect `success`, `diagnostic`, `node`, `ast()`, `value()` or `results()` as needed.

Add names for AST structure, pure guards for recognition rejection and semantic
validators for selected meaning. Use `onCommit()` and explicit `commit()` for
effects. Configure [trivia](trivia.md) at boundaries and between items explicitly.
Use [Rulesets](builder.md) for named rules/factories and
[RFC imports](libraries.md) for reusable protocol syntax.

Run `php docs/examples/execution.php` for verified runtime examples. The
[grammar-authoring guide](grammar-authoring.md) covers the immutable facade and
frozen graphs, with checked arithmetic/RFC equivalence examples. Use the
[ABNF subset](abnf.md) to compile supported authoring text into that same runtime.
See the [breaking-release guide](breaking-release.md) for complete caller changes.
