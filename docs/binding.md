# Binding expansion

`Extensions\Binding` is the opt-in application-value authoring vocabulary. It is
bundled with Comprehend and uses the shared parser graph, recognition, immutable
syntax trees, cached projections and explicit commit effects.

```php
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Binding;

$syntax = C::syntax()->extend(new Binding());
// Equivalent shortcut: $syntax = Binding::syntax();
$number = $syntax->integer()->mapText('intval');
$list = $number->separatedBy(',')->between('[', ']');
$list->parse('[1,2]')->value(); // [1, 2]
```

Registration is local and immutable. Global `C::collect()` and unextended
expressions' `map()` or `between()` calls are unavailable. Existing vocabularies
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
| `valueChoice(...)` | Selected alternative's value |
| `manyValues()`, `someValues()`, `repeatValues(min, max)` | List of accepted item values |
| `optionalValue(default: null)` | Accepted value or literal default |
| `separatedBy(separator, ...)` | List of item values, excluding separators |
| `thenIgnore(next)`, `ignoreThen(next)`, `between(open, close)` | Selected operand's value |

Collection, choice, repetition, optional and separated-list operations have both
vocabulary factories and fluent forms. Mapping and delimiter selection are
fluent operations. Factories accept literal strings, facades and Parser objects;
low-level constructors accept Parser objects explicitly.

Concrete extraction classes are `Collect`, `ValueChoice`, `ValueRepeat`,
`OptionalValue`, `ValueSequence` and `SeparatedList` under
`Vanderlee\Comprehend\Extensions\Binding`. They can be constructed directly
without installing a vocabulary. The former extraction classes under
`Parser\Structure` moved without compatibility aliases.

Core retains ordinary sequence/choice/repetition, separated-list recognition,
the `mapValue()` projection primitive, low-level projection composition,
named collections, validation, result access and explicit effects. Core
`Parser\Structure\SeparatedList` now defaults to matched text; Binding's subclass
adds list-value projection. Expression and recovery expansions reuse the Binding
classes where they need projections; their registration does not silently
activate Binding syntax names.

## Static analysis

Enable core PHPStan support and Binding's optional support together:

```yaml
includes:
    - vendor/vanderlee/comprehend/phpstan-extension.neon
    - vendor/vanderlee/comprehend/extensions/Binding/phpstan.neon
```

This enables signatures and value inference for Binding authoring. It does not
register runtime syntax or prove that an arbitrary Syntax instance installed
Binding; applications must opt in explicitly. Core-only users need only the
first configuration. Dynamic custom vocabularies keep their documented mixed
fallback. PHPStan is a development tool, not a runtime dependency.

See [the application tutorial](application-values.md),
[value composition](semantic-values.md), [delimiter selection](value-selection.md)
and [separated lists](separated-lists.md) for recognition and lifecycle contracts.
Run `php docs/examples/application-values.php` for a checked example.
