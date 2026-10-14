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

Run `php docs/examples/execution.php` for verified examples. Arithmetic object
and Ruleset examples are also exercised in `tests/examples`. The dedicated
facade, frozen graph and frontend remain later phases.
