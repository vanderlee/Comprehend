# Extraction expansion

`Extensions\Extraction` is the automatically loaded application-value vocabulary. It is
bundled with Comprehend and uses the shared parser graph, recognition, immutable
syntax trees, cached projections and explicit commit effects.

```php
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Extraction;

$syntax = C::syntax();
$number = $syntax->integer()->mapText('intval');
$list = $number->separatedBy(',')->between('[', ']');
$list->parse('[1,2]')->value(); // [1, 2]
```

Global `C::collect()` and expressions' `map()` or `mapText()` calls load the
extension automatically. Explicit registration remains local and immutable;
`Extraction::syntax()` is still supported. Use `$syntax->of($expressionOrParser)` to
attach the vocabulary to an existing facade, rule reference or object parser.
Facade inputs retain their semantic value type; object-parser inputs remain
conservatively mixed in static analysis.

Parser implementations live in `Extraction\Parser`; static authoring signatures use
ordinary PHPDoc in core `Syntax` and `Syntax\ValueMappings`.

## Operations

| Operation | Selected value |
| --- | --- |
| `map(callable)` | Transform the current value, composing lazily |
| `mapText(callable)` | Replace projection using original matched text |
| `mapWith(next, combine)` | Combine the current and next parser's values |
| `foldLeft(step, combine, min: 0, max: null)` | Reduce a head and repeated steps from left to right |
| `collect(...)` | Tuple of direct operand values |
| `record(fields)` | Array of direct field values under the supplied keys |
| `valueChoice(...)` | Selected alternative's value |
| `manyValues()`, `someValues()`, `repeatValues(min, max)` | List of accepted item values |
| `optionalValue(default: null)` | Accepted value or literal default |

Collection, choice, repetition and optional operations have both vocabulary factories
and fluent forms. Mapping and fold operations are fluent. Factories accept literal strings,
facades and Parser objects; low-level constructors accept Parser objects explicitly.

`mapWith()` evaluates the left value, the right value, and the combiner in that
order, lazily on the first value read. `foldLeft()` evaluates its head first,
then each accepted step followed by its combiner. Zero steps return the head
unchanged, including null or an object. Bounds count steps, excluding the head;
the combiner returns the next accumulator of the head's value type. No intermediate
step-value array is built, and a throwing combiner prevents later step values
from being evaluated. Results and exceptions are cached per recognition.

```php
$number = Extraction::syntax()->integer()->mapText('intval');
$minus = Extraction::syntax()->text('-')->ignoreThen($number);
$subtraction = $number->foldLeft($minus, static fn(int $a, int $b): int => $a - $b);
$subtraction->parse('10-3-2')->value(); // 5: (10 - 3) - 2
```

These operations reuse Sequence and Repeat recognition: all source syntax and
selected effects remain in the tree and lifecycle graph. A failed optional step
rolls back its input, diagnostics, trivia and effects; prefix parsing can leave an
incomplete operator unconsumed, while full-input parsing rejects that leftover
input. Required-step failures retain their diagnostics. Steps must consume input
unless `max: 0` disables repetition. Ordinary sequence spacing applies between
the head and tail as well, including an empty tail; use zero-or-more trivia when
spacing is optional. Callbacks never choose syntax or run during recognition.

MapWith and FoldLeft are also available as low-level constructors:
`new MapWith(Parser $left, Parser $right, callable $combine)` and
`new FoldLeft(Parser $head, Parser $step, callable $combine, int $min = 0, ?int $max = null)`.
They live in `Extensions\Extraction\Parser`; FoldSteps is an internal repetition
implementation. Both fluent names load automatically but are not exposed as
Syntax factories. Run [the pipeline and fold example](examples/value-pipelines.php).

`record()` is a vocabulary factory accepting an ordered array of field parsers.
Fields recognize in insertion order, and their lazy, cached values retain the
original string or integer keys. Automatic spacing contributes source text and
syntax nodes but no extra fields. Optional fields retain their key with the
configured default, including null. An empty record recognizes empty input and
returns `[]`.

```php
$record = Extraction::syntax()->record([
    'name' => Extraction::syntax()->readWhileRange('a', 'z', min: 1),
    'count' => Extraction::syntax()->integer()->mapText('intval'),
])->spacing(C::readWhileSet(' '));
$record->parse('item 12')->value(); // ['name' => 'item', 'count' => 12]
```

Fields may be literal strings, facades or Parser objects, including nested
records. Projection does not run effects; selected effects require `commit()`.
Static analysis describes records as `array<array-key, mixed>` rather than keyed shapes. Calling
`->record(fields)` on an existing expression follows ordinary terminal chaining:
it appends the record and the enclosing sequence returns raw text. Use the
vocabulary factory to obtain the record's value directly.
Run `php docs/examples/record.php` for a checked example.

Concrete extraction classes are `Collect`, `Record`, `ValueChoice`, `ValueRepeat`,
`OptionalValue`, `MapWith` and `FoldLeft` under `Vanderlee\Comprehend\Extensions\Extraction\Parser`. They can
be constructed directly without installing a vocabulary.

Core provides `separatedBy()`, `thenIgnore()`, `ignoreThen()` and `between()` with
the same item-value and operand-selection contracts whether Extraction is installed
or not. Their object parsers are `Parser\Structure\SeparatedList` and
`Parser\Structure\ValueSequence`. Core also retains ordinary sequence/choice/repetition,
the `mapValue()` projection primitive, named collections, validation and explicit
effects. Extraction adds explicit application-value extraction and transformation.
Expression and recovery extensions reuse extraction classes where needed without
activating Extraction syntax names.

The extension was renamed from `Binding` to `Extraction`; update imports.
No compatibility aliases are provided.

## Static analysis

No PHPStan extension or configuration include is required. Remove the former
`phpstan-extension.neon` and `extensions/Extraction/phpstan.neon` includes.
PHPDoc declares the bundled vocabulary for analysis; runtime dispatch discovers
it automatically. Custom vocabulary overrides retain their own runtime contracts.
Mappings and pipelines infer callback return types; folds preserve the head's
accumulator type and check compatible callback arguments/returns. Records, collections and overloaded factory
operations have conservative types. See [static value types](semantic-values.md#static-value-types).
PHPStan is a development tool, not a runtime dependency.

See [the application tutorial](application-values.md),
[value composition](semantic-values.md), [delimiter selection](value-selection.md)
and [separated lists](separated-lists.md) for recognition and lifecycle contracts.
Run `php docs/examples/application-values.php` for a checked example.
