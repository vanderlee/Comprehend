# Cached semantic output

## Composing application values

These helpers belong to the optional [Extraction expansion](extraction.md).
Use `Extraction::syntax()` or `C::syntax()->extend(new Extraction())` to author them.

Follow [Parse application values](application-values.md) for a complete domain-object
example with optional settings, alternatives and nested lists.

`map(callable(mixed): mixed)` transforms the current semantic value. Unlike
`mapText()` and `mapValue()`, consecutive `map()` calls compose in order. Every
stage is lazy and caches its value or thrown error for that recognition. Calling
`mapText()` or `mapValue()` replaces the projection and clears earlier transforms.
Low-level Parser objects also expose `map()`.

`$syntax->collect(...$operands)` recognizes a sequence and returns an ordered
list of its direct operand values. Automatic trivia is excluded; nested collects
remain nested. At least one operand is required. `$syntax->valueChoice(...$alternatives)`
recognizes an ordinary choice but forwards the selected alternative's value,
including null. First/longest/shortest policies and diagnostics remain unchanged;
discarded alternatives' mappings are not evaluated to select a match.

```php
use Vanderlee\Comprehend\Extensions\Extraction;


$amount = Extraction::syntax()->integer()->mapText('intval');
$currency = Extraction::syntax()->valueChoice('USD', 'EUR');
$money = Extraction::syntax()->collect($currency, $amount)
    ->map(static fn(array $parts): object => (object) [
        'currency' => $parts[0],
        'amount' => $parts[1],
    ])
    ->spacing(Extraction::syntax()->text(' ')->many());

$result = $money->parse('USD 12');
$result->value()->amount; // 12 (int)
$result->rawText();       // 'USD 12'
```

Both factories accept facades, Parser objects, or literal strings. Syntax
vocabularies expose them too. Fluent `$a->collect($b)` and `$a->valueChoice($b)`
include `$a` as the first operand and preserve nesting. Ordinary `sequence()`,
`then()` and `choice()` retain their raw-text defaults.
Low-level constructors are `new Collect(Parser ...$operands)` and
`new ValueChoice(Parser ...$alternatives)` in `Extensions\Extraction`.

These operations preserve the complete CST, source spans, named collections,
semantic validators and explicit commit effects. Value inspection never commits.
Mapping a collected sequence evaluates operand values in recognition order.
An outer `mapText()` or `mapValue()` can replace collection/choice projection
without evaluating its operand mappings. Run `php docs/examples/value-composition.php`.

These names are local expansion registrations, not reserved core built-ins.
Installing Extraction follows the ordinary Syntax replacement and isolation rules.

## Repeated and optional values

Use `$item->manyValues()` for zero or more item values, `$item->someValues()` for
one or more, or `$item->repeatValues(min: 2, max: 4)` for explicit bounds. The
default projection is an ordered list, including nulls and nested item values;
automatic trivia is excluded. An empty accepted repetition yields `[]`.

`$item->optionalValue(default: 'missing')` returns the accepted item's value when
present, and the supplied default when absent. The default is null if omitted.
Presence is determined by recognition: accepted null, false, zero and empty-string
values do not trigger the default. Defaults are literal values, including callable
and object values: callables are never invoked, and objects are not cloned or
constructed afresh per parse. Use a pure `map()` if a fresh application object is needed.

```php
use Vanderlee\Comprehend\Extensions\Extraction;

$digit = Extraction::syntax()->range('0', '9')->mapText('intval');
$digit->manyValues()->parse('123')->value(); // [1, 2, 3]
$digit->manyValues()->parse('')->value();    // []
$digit->someValues()->parse('')->success;   // false
$digit->optionalValue(default: 9)->parse('')->value(); // 9
$digit->optionalValue(default: 9)->parse('0')->value(); // 0
```

Static and Syntax forms accept facades, Parser objects or literal strings:
`C::manyValues($item)`, `C::someValues($item)`,
`C::repeatValues($item, min: 2, max: 4)` and
`C::optionalValue($item, default: 'missing')`. These four names are reserved
built-ins; custom Syntax registrations with those names must be renamed.
Existing `many()`, `some()`, `repeat()`, `maybe()` and `optional()` keep their
raw-text defaults. Low-level constructors in `Extensions\Extraction` are
`new ValueRepeat(Parser $item, int $min = 0, ?int $max = null)` and
`new OptionalValue(Parser $item, mixed $default = null)`.

All use the existing greedy repetition runtime: a failed extension rolls back its
syntax, spacing, validators and effects. Prefix parsing may leave an incomplete
item unconsumed; full-input parsing rejects leftover input. These operations do
not recover malformed optional fields. Required failures retain ordinary repetition
diagnostics; termination probes do not pollute expectations. Items must consume
input, including optional items. Known nullable items are rejected during freezing,
with runtime progress checks too; a zero maximum skips the item entirely.

Values and mapping errors remain lazy and cached per recognition. `map()` composes
with the default list/item projection; `mapText()` and `mapValue()` replace it.
Accepted item validation, collections and explicit commit effects are preserved.
CST nodes, trivia and byte spans remain intact. Run
`php docs/examples/value-repetition.php` for a complete application object example.

## Static value types

The facade, frozen grammar and parse result carry a PHPDoc value type:
`Comprehend<T>`, `FrozenGrammar<T>` and `ParseResult<T>`. Mapping signatures are
provided through PHPDoc for core projections and Extraction's separate PHPStan
reflection support for its vocabulary. PHPStan is not a runtime dependency.

For PHPStan 2.1, include the bundled extension in your project's configuration:

```yaml
includes:
    - vendor/vanderlee/comprehend/phpstan-extension.neon
    - vendor/vanderlee/comprehend/extensions/Extraction/phpstan.neon
```

The repository's configuration includes both. Extraction support models magic
factory and fluent calls, including list and optional-value projections. IDE
support depends on its PHPDoc and PHPStan integration; enabling PHPStan support
does not itself install an IDE plugin or activate Extraction at runtime.

```php
use Vanderlee\Comprehend\Extensions\Extraction;

$number = Extraction::syntax()->integer()->mapText('intval'); // Comprehend<int>
$doubled = $number->map(static fn(int $n): int => $n * 2);
$list = $doubled->manyValues();           // Comprehend<list<int>>
$optional = $number->optionalValue(default: false); // Comprehend<int|false>
$result = $list->freeze()->parse('123');  // ParseResult<list<int>>
$values = $result->value();              // list<int>|null
```

`map()` checks its callback against the current value type and infers the new
type. `mapText()` accepts string callbacks; `mapValue()` accepts ParseResult
callbacks for source/result inspection, not access to an earlier value projection.
`someValues()` infers a nonempty list; bounded repetition and separated lists
conservatively infer lists even with positive minimums. Optional values include
the supplied default's type, or null when it is omitted.

Explicit `collect()` operands infer tuple positions, and `valueChoice()` unions
alternative value types. Names, spacing, delimiters and `thenIgnore()` preserve
the selected value type. `ignoreThen()` forwards the right operand's type.
`freeze()`, complete and prefix parsing, `value()` and `output()` retain it.
The application tutorial's `jobParser()` declares `Comprehend<Job>` and is checked
alongside the inference fixtures.

`value()` and `output()` include null for failure, even after inspecting the boolean
`success` property. Narrow the value itself when needed; successful parsers may
also produce null. Static types do not guarantee semantic validity or prevent
mapping exceptions. Low-level parser inputs, dynamic custom vocabularies and
unpacked operand lists conservatively fall back to mixed; arbitrary runtime
syntax registrations are not inferred. `FrozenGrammar::from()` remains mixed;
use a facade's `freeze()` to retain its value type.

Run `make phpstan` to check the implementation, positive type assertions and
rejection of incompatible callbacks. Without Make, run the analysis command in
[contributing](contribute.md) followed by `php tools/check-value-types.php`.

## Mappings and named collections

`mapValue(callable(ParseResult): mixed)` adds a lazy typed mapping. `value()`
caches scalars, null, arrays, objects and thrown errors for that recognition.
Without a mapping it returns raw text. Transparent runtime wrappers preserve
their accepted child's mapping unless explicitly mapped. Failed results return
null; abandoned alternatives do not evaluate their mappings automatically.

Value-selecting sequences provide a different default: `thenIgnore()` returns
the first operand's value, `ignoreThen()` the second, and `between()` the body's.
They retain the full outer raw text, syntax and lifecycle. See
[delimiters and value selection](value-selection.md).

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

Concatenation accepts scalars, null, resources and objects implementing `Stringable`,
using their PHP string representation. Arrays and other objects raise
`UnexpectedValueException` when the collection is read; the error is cached.
When concatenating onto a pushed collection, only its last item is concatenated.
`setResult()` and `pushResult()` continue to retain arbitrary values.

Definition processors receive the local result collection and produce a named
value. They replace local collection output, retaining child syntax, validation
and effects. Processor results/errors are cached per recognition; they do not
depend on mutable parent output. Definition validators receive ParseResult under
the selected [semantic lifecycle](lifecycle.md).

Later grammar mutations do not change accepted mappings, collection operations,
names, validators or effects. Builders themselves remain mutable until Phase 3;
each separate recognition has its own output caches.
