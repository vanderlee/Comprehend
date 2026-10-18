# Tutorial

Build a grammar, parse input, then extract the result. Start with the
[quick start](index.md#quick-start), or run
`php docs/examples/quick-start.php`.

For a complete value-first walkthrough, follow [Parse application values](application-values.md):
construct a domain object with an optional field, value alternatives and nested lists.

Build a dice grammar by combining typed numbers, a literal delimiter and an
optional signed modifier. The Numbers and Extraction extensions load automatically:

~~~php
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Numbers\SignPolicy;

$modifier = C::integerValue(-100, 100, allowLeadingZeros: true, sign: SignPolicy::REQUIRED)
    ->optionalValue(default: 0);
$roll = C::collect(
    C::integerValue(1, 100)->thenIgnore('d'),
    C::integerValue(2, 1000)->named('sides'),
    $modifier,
)->map(static fn(array $parts): array => [
    'dice' => $parts[0],
    'sides' => $parts[1],
    'modifier' => $parts[2],
]);

$result = $roll->parse('2d6+3');
if ($result->ok) {
    $value = $result->output(); // ['dice' => 2, 'sides' => 6, 'modifier' => 3]
    $text = $result->rawText(); // '2d6+3', unchanged source
}
~~~

`integerValue()` returns an integer within its bounds. `thenIgnore('d')`
recognizes the literal delimiter and keeps the count's value. `collect()` gathers
the three values in order; the pure `map()` callback gives them descriptive keys.
`optionalValue(default: 0)` handles an absent modifier: `1d20` has modifier zero,
while `2d6-3` has modifier -3. This parses a roll description without rolling dice.
Counts range from 1–100, sides from 2–1000, and modifiers from -100 to 100.
`allowLeadingZeros: true` permits signed zero and leading zeros in the modifier.
The count and lowercase `d` are required.

`ok` and `output()` are short forms of `success` and `value()`. Output is original
text unless a mapping is configured, and null on failure. Successful mappings may
also return null, so check `ok` rather than using output as a success flag.

Allow spacing between grammar items for one call and inspect a named syntax node:

~~~php
$result = $roll->parse('2d6 +3', spacing: C::text(' ')->many());
if ($result->success) {
    $sidesText = $result->ast()->firstNamed('sides')->rawText(); // '6'
}
~~~

`parse()` requires complete input. Use `matchPrefix()` to allow
a remainder. Both accept UTF-8 strings or explicit Source objects; use ByteSource
for arbitrary bytes. Check `success` before extracting values; inspect
`diagnostic` on failure.

Per-call spacing supplies a default between items without changing the grammar.
The modifier's sign and digits remain contiguous, as in `+3`.
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
