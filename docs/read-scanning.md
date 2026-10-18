# Reading and skipping runs

Read scanners consume a contiguous run in one terminal invocation and return its
original text. The bundled Optimizer extension supplies all read/skip factories
and loads automatically on first use. They use the same checked runtime as other
terminals and remain available with `FreezeFlags::NONE`; that flag disables graph
optimization, not explicitly authored scanners.

Direct parser classes live in `Extensions\Optimizer\Parser`: `ReadRun` (the
abstract base), `ReadWhile`, `ReadUntil`, `ReadWhileSet`, `ReadWhileRange`, and
`ReadRest`. Their former `Parser\Terminal` names have no compatibility aliases.
Scanner CST kinds use the extension class names. Factory signatures and static,
scoped Syntax, and fluent calls retain their behavior. These factory names are
discoverable rather than reserved core names and can be overridden in a local
Syntax vocabulary.

```php
use Vanderlee\Comprehend\Comprehend as C;

C::readWhile(static fn(string $character): bool => ctype_alpha($character), min: 1);
C::readWhileSet(" \t\r\n");
C::readWhileRange('0', '9', min: 1, max: 8);
C::readUntil(';');
C::readUntil('*/');
C::readUntil(static fn(string $character): bool => $character === ';');
C::readRest();
```

`min` defaults to zero and `max` to null (unlimited). Bounds count Unicode code
points for UTF-8 sources and octets for byte sources, while offsets and spans
remain byte-based. Fewer than `min` accepted units causes failure without
consumption, with diagnostics at the observed stopping position. Reaching `max`
succeeds without probing the next unit. Negative bounds or `max < min` are
rejected at construction.

`readUntil()` leaves its delimiter unconsumed. It allows an empty prefix and
succeeds at EOF if the delimiter is absent, provided `min` is satisfied. A string
always denotes a nonempty literal, even if it names a PHP function. Pass a closure
or other non-string callable for predicates. A delimiter beginning exactly at a
finite maximum need not be inspected. These bounds count content, not delimiter
units. To require and consume the delimiter while retaining only the content:

```php
use Vanderlee\Comprehend\Extensions\Extraction\Extraction;

$content = Extraction::syntax()->readUntil(';')->thenIgnore(';');
$content->parse('hello;')->value(); // 'hello'
```

Predicates must be pure and return bool. They receive one raw source unit,
including whitespace and newlines, without case folding. Set membership, ranges,
and literal delimiters inherit the scoped case policy and support local overrides.
Ranges are inclusive and may have one null endpoint; the other endpoint must be
one source unit. Bounds must remain ordered after case folding, and folded range
units must remain single code points. Source-dependent validation happens when
recognition starts, so arbitrary byte sets/delimiters remain usable in byte mode.

`skipWhile(predicate, min: 0, max: null)`,
`skipUntil(delimiter, min: 0, max: null)`, and `skipRest()` recognize the same
input as their read counterparts but return null. Skipping only discards the
semantic value: source spans, CST text, named AST content, diagnostics, and
explicit commit effects remain available. Like other terminal factories, these
methods work through `C::syntax()` and as fluent terminal chaining.

Each scanner produces a single CST terminal node for the entire run. It does not
insert spacing between units. Use a scanner as the grammar's spacing parser to
scan intervening trivia, or compose it with other parsers normally. Nullable
scanners must not be placed inside unbounded repetition; use `min: 1` when
progress is required. Work limits account for scanning and delimiter lookahead.

Case-sensitive sets, ranges and literal delimiters use cached regex patterns for
in-memory input. The generated patterns preserve empty matches, unit-count bounds,
byte positions and the single-terminal CST shape. Predicates still run as PHP
callbacks; `readRest()` uses direct source slicing rather than a regex.

The source-unit loop remains the fallback for case-insensitive matching, file or
custom sources, finite work budgets, finite maxima above PCRE's repeat limit,
generated atoms larger than 8 KiB, and regex engine failures. This keeps scoped
case folding, cooperative work-limit positions and source access behavior intact.
Skip methods share the same recognition paths as their read counterparts.

See [the runnable example](examples/read-scanning.php).
