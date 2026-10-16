# Cached semantic output

## Composing application values

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
