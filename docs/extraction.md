# Extraction expansion

`Extensions\Extraction` is the opt-in application-value authoring vocabulary. It is
bundled with Comprehend and uses the shared parser graph, recognition, immutable
syntax trees, cached projections and explicit commit effects.

```php
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Extraction;

$syntax = C::syntax()->extend(new Extraction());
// Equivalent shortcut: $syntax = Extraction::syntax();
$number = $syntax->integer()->mapText('intval');
$list = $number->separatedBy(',')->between('[', ']');
$list->parse('[1,2]')->value(); // [1, 2]
```

Registration is local and immutable. Global `C::collect()` and unextended
expressions' `map()` or `mapText()` calls are unavailable. Existing vocabularies
and expressions remain unchanged. Use `$syntax->of($expressionOrParser)` to
attach the vocabulary to an existing facade, rule reference or object parser.
Facade inputs retain their semantic value type; object-parser inputs remain
conservatively mixed in static analysis.

## Operations

| Operation | Selected value |
| --- | --- |
| `map(callable)` | Transform the current value, composing lazily |
| `mapText(callable)` | Replace projection using original matched text |
| `collect(...)` | Tuple of direct operand values |
| `record(fields)` | Array of direct field values under the supplied keys |
| `valueChoice(...)` | Selected alternative's value |
| `manyValues()`, `someValues()`, `repeatValues(min, max)` | List of accepted item values |
| `optionalValue(default: null)` | Accepted value or literal default |

Collection, choice, repetition and optional operations have both vocabulary factories
and fluent forms. Mapping operations are fluent. Factories accept literal strings,
facades and Parser objects; low-level constructors accept Parser objects explicitly.

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
PHPStan infers keyed array shapes for statically known field maps. Calling
`->record(fields)` on an existing expression follows ordinary terminal chaining:
it appends the record and the enclosing sequence returns raw text. Use the
vocabulary factory to obtain the record's value directly.
Run `php docs/examples/record.php` for a checked example.

Concrete extraction classes are `Collect`, `Record`, `ValueChoice`, `ValueRepeat`
and `OptionalValue` under `Vanderlee\Comprehend\Extensions\Extraction`. They can
be constructed directly without installing a vocabulary.

Core provides `separatedBy()`, `thenIgnore()`, `ignoreThen()` and `between()` with
the same item-value and operand-selection contracts whether Extraction is installed
or not. Their object parsers are `Parser\Structure\SeparatedList` and
`Parser\Structure\ValueSequence`. Core also retains ordinary sequence/choice/repetition,
the `mapValue()` projection primitive, named collections, validation and explicit
effects. Extraction adds explicit application-value extraction and transformation.
Expression and recovery extensions reuse extraction classes where needed without
activating Extraction syntax names.

The extension was renamed from `Binding` to `Extraction`; update imports and the
optional PHPStan configuration path. No compatibility aliases are provided.

## Static analysis

Enable core PHPStan support and Extraction's optional support together:

```yaml
includes:
    - vendor/vanderlee/comprehend/phpstan-extension.neon
    - vendor/vanderlee/comprehend/extensions/Extraction/phpstan.neon
```

This enables signatures and value inference for Extraction authoring. It does not
register runtime syntax or prove that an arbitrary Syntax instance installed
Extraction; applications must opt in explicitly. Core-only users need only the
first configuration. Dynamic custom vocabularies keep their documented mixed
fallback. PHPStan is a development tool, not a runtime dependency.

See [the application tutorial](application-values.md),
[value composition](semantic-values.md), [delimiter selection](value-selection.md)
and [separated lists](separated-lists.md) for recognition and lifecycle contracts.
Run `php docs/examples/application-values.php` for a checked example.
