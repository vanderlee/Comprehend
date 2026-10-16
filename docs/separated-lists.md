# Separated lists

`$item->separatedBy($separator, min: 0, max: null, allowLeading: false,
allowTrailing: false)` recognizes consuming items separated by another parser.
The facade accepts literal strings for either operand; static
`Comprehend::separatedBy($item, $separator, ...)` and Syntax vocabularies expose
the same operation. Low-level object grammars use
`new SeparatedList(Parser $item, Parser $separator, ...)`.

```php
use Vanderlee\Comprehend\Comprehend as C;

$numbers = C::integer()->mapText(static fn(string $text): int => (int) $text)
    ->separatedBy(',', min: 1, allowTrailing: true);
$result = $numbers->parse('10,20,');
$result->value();   // [10, 20], lazy and cached
$result->rawText(); // '10,20,'
```

Bounds count items, not separators. Negative bounds and maximums below the
minimum are rejected. A zero maximum recognizes an empty list without attempting
either operand. Known nullable items are rejected when freezing; every successful
item must consume input at runtime too. A separator may be zero-width because
items supply progress.

Empty lists are accepted when `min` is zero. Leading and trailing separators
are disabled by default and enabled independently. Either boundary separator
requires at least one item: a separator alone is never accepted as a complete
list, even with both options enabled. Repeated separators do not stand for empty
items. At the maximum item count, an allowed trailing separator can still be
consumed, but no additional item is attempted.

## Prefixes and spacing

The parser is greedy and uses the same prefix semantics as repetition. Once the
minimum is met, a failed separator/item extension ends the list. With an item
parser for `a`, `matchPrefix('a,x')` accepts `a`; with trailing separators enabled
it accepts `a,`. Complete-input `parse()` rejects both because `x` remains.
Use explicit closing delimiters when embedding a list in a larger document.
There is no implicit syntax cut or retry after a later suffix failure.

Scoped spacing applies between items and separators, including zero-width
separators. Boundary whitespace remains explicit. Local `spacing()` and
`lexeme()` policies work as on sequences and repetitions. Spacing before an
abandoned pair is rolled back; with an accepted trailing separator, spacing
before that separator is retained, while spacing after it is not. Ordinary
failed extension probes do not pollute diagnostics; failures needed to satisfy
the minimum retain their expectations. Abandoned item/separator diagnostics are
also retained separately for a failed enclosing parse or complete-input check.
The farthest failure wins, with tied expectations combined. Successful prefix
and complete parses still suppress ordinary termination probes; this changes
diagnostics only, without committing to a separator or consuming failed items.

## Trees and lifecycle

The CST retains accepted items, separators and automatic trivia in source order,
with original byte spans. Separator names and tags retain their normal AST
behavior; separators are not implicitly trivia. Raw text always slices the
accepted source span.

The default value is a list of item values. Item mappings are evaluated lazily,
and separator mappings are not read to build the list. A list-level `mapValue()`
or `mapText()` replaces this default projection. Each recognition has independent
value and error caches.

Accepted item and separator named collections, semantic validators and explicit
commit effects participate normally. Automatic trivia contributes validation
and effects, but no named collections. Abandoned extensions contribute neither
selected validation nor committed effects. Parsing and reading values never
commit effects.

Run `php docs/examples/separated-lists.php` for a checked example.
