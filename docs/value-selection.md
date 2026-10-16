# Delimiters and value selection

These fluent methods recognize every operand and select one operand's lazy value:

| Method | Recognition order | Default value |
| --- | --- | --- |
| `$a->thenIgnore($b)` | A, B | A's value |
| `$a->ignoreThen($b)` | A, B | B's value |
| `$body->between($open, $close)` | Open, body, close | Body's value |

Operands accept Comprehend expressions, Parser objects, or literal strings. Syntax
vocabularies expose the same fluent methods. Existing `then()` retains its ordinary
sequence behavior; it does not select a child value.

These names are now reserved built-ins. Custom Syntax vocabularies that previously
defined `between` must rename that fluent (for example to `surround`).

```php
use Vanderlee\Comprehend\Comprehend as C;

$number = C::integer()->mapText(static fn(string $text): int => (int) $text);
$numbers = $number->separatedBy(',')->between('[', ']');
$result = $numbers->parse('[10,20]');
$result->value();   // [10, 20], lazy and cached
$result->rawText(); // '[10,20]'

$number->thenIgnore(';')->parse('42;')->value(); // 42
C::text('return:')->ignoreThen($number)->parse('return:42')->value(); // 42
C::text('hello')->between('(', ')')->parse('(hello)')->value(); // 'hello'
```

## Syntax, spacing and lifecycle

All operands must recognize successfully, including closing delimiters. Prefix
parsing permits remaining input only after the complete sequence succeeds.
Diagnostics, work limits and scoped spacing use the ordinary sequence runtime.
Spacing is inserted between operands; leading and trailing document trivia remain
explicit. `lexeme()` suppresses inserted spacing and `spacing(null)` disables it
locally. Value-selecting sequences retain their composition boundaries.

The outer span and `rawText()` include every accepted operand and intervening
trivia. The CST retains all syntax, and naming and tags keep their normal AST
behavior. Selection does not implicitly mark delimiters as trivia or hide named
nodes. Selecting an unmapped body returns the body's own raw text, rather than
the surrounding delimiters. Empty selected text and null mapped values remain
valid values.

Reading the selected value evaluates only that operand's value and caches its
output or exception. Unselected value mappings are not evaluated merely to obtain
the selected value. A later list/sequence-level `mapValue()` or `mapText()` replaces
the default selection and receives the full outer result or raw text respectively.

All accepted operands still contribute named collections, semantic validation and
explicit commit effects in recognition order. An unselected operand's validator
may explicitly read its own value. Automatic trivia retains the existing validation
and effect behavior. Failed or discarded sequences never commit their effects.

## Low-level object authoring

`new ValueSequence(int $valueIndex, Parser ...$parsers)` uses zero-based operand
positions, excluding automatic trivia. For example:

```php
use Vanderlee\Comprehend\Parser\Structure\ValueSequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;

$wrapped = new ValueSequence(1, new Text('('), new Text('hello'), new Text(')'));
```

Positions must refer to an existing operand. Mutable container operations address
the current operand order; removing enough operands to invalidate selection raises
a grammar error when freezing, or an exception during successful direct recognition.
Snapshots detach operands and retain independent per-recognition value caches.

Run `php docs/examples/value-selection.php` for checked examples.
