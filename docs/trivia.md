# Trivia retention and literal spacing

Concrete nodes expose `isTrivia`. Accepted automatic spacing appears as marked
children between the matched items in Sequence and Repeat. Its original spans,
whitespace and comments are retained, without adding legacy token children or
named results. Repetition's abandoned final spacer is omitted, and its effects
remain uncommitted. Successful zero-width spacers have zero-width trivia nodes.

## Explicit boundaries

Leading and trailing trivia remain explicit grammar elements. `parseAll()` checks
EOF without trimming or skipping additional input. `Parser::trivia()` wraps a
parser in `Directive\Trivia`, marking its concrete root while preserving the
wrapped parser's ordinary token, result and callback behavior. It does not choose
a whitespace grammar or change how much the parser consumes.

```php
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Regex;
use Vanderlee\Comprehend\Source\Utf8Source;

$trivia = (new Regex('~(?:\s|/\*[^*]*\*/)*~'))->lexeme()->trivia();
$body = (new Sequence('a', 'b'))->spacing($trivia);
$document = (new Sequence($trivia, $body, $trivia))->spacing(null);
$result = $document->parseAll(new Utf8Source(' /*lead*/ a /*between*/ b /*tail*/ '));
$result->success; // true
$result->node->children[0]->isTrivia; // true: leading trivia
$result->node->children[1]->children[1]->isTrivia; // true: intervening trivia
$result->node->children[2]->isTrivia; // true: trailing trivia
```

The example comment pattern is deliberately simple; choose the comment grammar
your language needs. The outer sequence disables inherited automatic spacing so
its boundary parsers alone own the leading/trailing policy. Empty boundary trivia
still retains its actual source position. Parent raw text includes all accepted
trivia regardless of names or transformations. Sequence/Repeat concrete children
are in recognition order and include trivia; consumers must account for these
new children. `All` still has overlapping children, so use parent `rawText()` for
general source reconstruction.

## Lexemes

`Parser::lexeme()` returns a `Directive\Lexeme` wrapper that disables automatic
spacing throughout the wrapped subtree, including nested spacing overrides.
It does not mutate the original parser. Explicit literal spaces still match.

```php
$literal = (new Sequence('a', 'b'))->spacing(' ');
$grammar = (new Sequence($literal->lexeme(), 'c'))->spacing(' ');
$grammar->parseAll(new Utf8Source('ab c'))->success;  // true
$grammar->parseAll(new Utf8Source('a b c'))->success; // false
$literal->parseAll(new Utf8Source('a b'))->success;  // true: original unchanged
```

Outer spacing before or after the lexeme follows the enclosing grammar's policy.
The suppression scope is restored after success, failure or exceptions. Lexeme
also works on the legacy string path. It differs from `spacing(null)`, which a
nested local override may re-enable. A Text terminal already recognizes its
literal without internal automatic spacing; lexeme is useful for composed
literals, repetitions and nested grammar rules.

## Compatibility

`Compatibility\LegacySpacing` owns the existing independent spacer recognition
used by `spacing(...)` and `Space`. Spacing remains required between items once
some input has been consumed, with no automatic leading or trailing skip. A
nullable spacer permits adjacent items; a required space still rejects them.
Legacy string matching and coordinate adapters preserve this acceptance policy,
token/result shapes and deferred selected spacer effects. Source-bound CSTs add
trivia children without changing those legacy projections.
