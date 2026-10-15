# Tutorial

Build a grammar, parse input, then extract the result. Start with the
[README quick start](../README.md#quick-start), or run
`php docs/examples/quick-start.php`.

For a typed value, attach a pure mapping before parsing:

~~~php
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Result\ParseResult;

$age = C::integer(0, 150)
    ->named('age')
    ->mapValue(static fn(ParseResult $r): int => (int) $r->rawText());

$result = $age->parse('42');
if ($result->success) {
    $value = $result->value(); // 42, cached
    $text = $result->rawText(); // '42', unchanged source
}
~~~

Combine parsers to recognize larger syntax. Strings in composition are literals:

~~~php
$declaration = C::sequence('age', ':', $age);
$result = $declaration->parse('age : 42', spacing: C::text(' ')->many());
if ($result->success) {
    $ageText = $result->ast()->firstNamed('age')->rawText(); // '42'
}
~~~

`parse()` requires complete input. Use `matchPrefix()` to allow
a remainder. Both accept UTF-8 strings or explicit Source objects; use ByteSource
for arbitrary bytes. Check `success` before extracting values; inspect
`diagnostic` on failure.

Per-call spacing supplies a default between items without changing the grammar.
It does not trim input, and local `spacing()` or `lexeme()`
directives take precedence. For repeated parsing, configure spacing before calling
`freeze()` once, then reuse that frozen grammar.

Names select AST structure. Mappings compute values lazily; they do not change
source text. Add pure `guard()` predicates for recognition rejection and
`validateSemantic()` for selected meaning. Use `onCommit()` and
explicit `commit()` only when effects are needed.
See [trivia](trivia.md) for boundary whitespace, [Rulesets](builder.md) for named
rules, and [RFC imports](libraries.md) for protocols.

The [grammar-authoring guide](grammar-authoring.md) explains immutable composition,
scoped syntax extensions and freezing. The [ABNF subset](abnf.md) compiles to the
same runtime. Run `php docs/examples/execution.php` for execution semantics.
