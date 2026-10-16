# Cached semantic output

## Composing application values

Follow [Parse application values](application-values.md) for a complete domain-object
example with optional settings, alternatives and nested lists.

`map(callable(mixed): mixed)` transforms the current semantic value. Unlike
`mapText()` and `mapValue()`, consecutive `map()` calls compose in order. Every
stage is lazy and caches its value or thrown error for that recognition. Calling
`mapText()` or `mapValue()` replaces the projection and clears earlier transforms.
Low-level Parser objects also expose `map()`.

`Comprehend::collect(...$operands)` recognizes a sequence and returns an ordered
list of its direct operand values. Automatic trivia is excluded; nested collects
remain nested. At least one operand is required. `Comprehend::valueChoice(...$alternatives)`
recognizes an ordinary choice but forwards the selected alternative's value,
including null. First/longest/shortest policies and diagnostics remain unchanged;
discarded alternatives' mappings are not evaluated to select a match.

```php
use Vanderlee\Comprehend\Comprehend as C;

$amount = C::integer()->mapText('intval');
$currency = C::valueChoice('USD', 'EUR');
$money = C::collect($currency, $amount)
    ->map(static fn(array $parts): object => (object) [
        'currency' => $parts[0],
        'amount' => $parts[1],
    ])
    ->spacing(C::text(' ')->many());

$result = $money->parse('USD 12');
$result->value()->amount; // 12 (int)
$result->rawText();       // 'USD 12'
```

Both factories accept facades, Parser objects, or literal strings. Syntax
vocabularies expose them too. Fluent `$a->collect($b)` and `$a->valueChoice($b)`
include `$a` as the first operand and preserve nesting. Ordinary `sequence()`,
`then()` and `choice()` retain their raw-text defaults.
Low-level constructors are `new Collect(Parser ...$operands)` and
`new ValueChoice(Parser ...$alternatives)` in `Parser\Structure`.

These operations preserve the complete CST, source spans, named collections,
semantic validators and explicit commit effects. Value inspection never commits.
Mapping a collected sequence evaluates operand values in recognition order.
An outer `mapText()` or `mapValue()` can replace collection/choice projection
without evaluating its operand mappings. Run `php docs/examples/value-composition.php`.

The names `map`, `collect` and `valueChoice` are reserved built-ins; custom Syntax
registrations using those names must be renamed.

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
$digit = C::range('0', '9')->mapText('intval');
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
raw-text defaults. Low-level constructors in `Parser\Structure` are
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
