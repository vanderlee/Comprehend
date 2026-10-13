# Canonical results and legacy coordinate adapters

Phase 1.4 introduces `Parser::parseSource(Source $source, int $byteOffset = 0)`.
It recognizes a prefix through the [source path](source-matching.md), resolves
selected callbacks, and returns a `Result\ParseResult`. Phase 1.5 adds explicit
[`matchPrefix()` and `parseAll()`](consumption.md); `parseSource()` remains a prefix alias.

## Canonical result contract

| Member | Meaning |
|---|---|
| `success` | Whether recognition succeeded |
| `span` | Immutable byte span retaining the original source object |
| `start`, `end` | Absolute byte boundaries of that span |
| `consumedByteLength` | `end - start`, always zero on failure |
| `farthestFailure` | Separate absolute byte position, or null if no relevant failure remains |
| `diagnostic` | Structured failure facts and lazy location/excerpt access, or null |
| `node` | Immutable concrete recognition tree on success, or null |
| `ast()` | Cached named AST projection with a full-span synthetic unnamed root, or null on failure |
| `value()` | Cached typed mapping, original text when unmapped, or null on failure |
| `rawText()` | Original span bytes, independent of semantic transformations |
| `token()` | Existing token projection, or null on failure |
| `results()` / `result($name = null, $default = null)` | Existing cached named/default result projections |

A failed result has span `[start, start)` even when recognition reached a later
position. Empty successes keep their actual position, including EOF. Coordinate
fields are read-only. Lazy token/value projections retain existing grammar and
match behavior; immutable CST and semantic lifetime changes remain phase 2 work.

```php
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\Utf8Source;

$parser = new Text("\u{00E9}a");
$result = $parser->parseSource(new Utf8Source("z\u{00E9}b"), 1);
$result->success;             // false
$result->start;               // 1
$result->end;                 // 1
$result->consumedByteLength;  // 0
$result->farthestFailure;     // 3, mismatch after é
$result->rawText();           // empty string
```

`matchSource()` still returns Success/Failure with its prior `length` behavior.
Source-bound matches now additionally expose `span` and `farthestFailure` in
canonical bytes. On Failure, `length` is progress, not consumption. Legacy
`match()` remains unchanged and has null source metadata. Constructing a
ParseResult from an old unbound match throws: reparse instead of guessing units.

Farthest failure is the farthest retained relevant failure. [Structured diagnostics](diagnostics.md) provide expected symbols, rule paths, lazy locations and source excerpts. Optional probes, negative lookahead children and normal repetition termination are suppressed. Failed choice alternatives can still contribute diagnostics beyond a successful span. Custom match factories that discard progress cannot yield information they never recorded.

## Explicit legacy coordinate policies

`Compatibility\LegacyParserAdapter` preserves Success/Failure, Token-compatible
objects, callback signatures and the array/JSON/XML projection shapes. It
reparses input with one declared policy rather than reinterpreting old results.

```php
use Vanderlee\Comprehend\Compatibility\CoordinatePolicy;
use Vanderlee\Comprehend\Compatibility\LegacyParserAdapter;

$parser = (new Text('a'))->token('letter');
$source = new Utf8Source("\u{00E9}a");
$bytes = (new LegacyParserAdapter($parser, CoordinatePolicy::BYTES))->match($source, 2);
$points = (new LegacyParserAdapter($parser, CoordinatePolicy::CODE_POINTS))->match($source, 1);
$bytes->token->offset;   // 2
$points->token->offset;  // 1
$points->token->text;    // a, sliced using original bytes
$points->span->start;    // 2: canonical metadata always stays byte-based
```

| Policy | String input mode | Input/output numeric units | Restrictions |
|---|---|---|---|
| `ASCII` (default) | ByteSource | Bytes/code points coincide | Rejects non-ASCII input; permits unmigrated parser subclasses on the ASCII bridge |
| `BYTES` | ByteSource | Byte offsets and lengths | Pass Utf8Source explicitly for text grammars; custom subclasses must declare source compatibility |
| `CODE_POINTS` | Utf8Source | Code-point offsets and lengths | Rejects ByteSource and malformed UTF-8; custom subclasses must declare source compatibility |

Passing a source preserves its mode and identity; the coordinate policy does
not override grammar mode rules. Code points are neither grapheme clusters nor
UTF-16 units. Every nested token and Parser callback uses the same policy.
Callback input/text, assignments and semantic values retain original bytes;
converted character offsets are never passed to `substr()` for extraction.
Code-point token views preserve raw text for array, JSON, XML and string output.
Persist the policy alongside serialized legacy data, whose old shape has no unit
field. New `span`/`farthestFailure` metadata remains canonical under all policies.

Failure `length` is converted diagnostic distance from start to farthest failure,
not consumed length. It can differ from old shortest-choice/partial-progress
lengths. A code-point policy rejects a diagnostic position without a text
boundary; it never rounds. Selected callback positions are checked before
effects resolve. Token projection remains lazy and conversion errors in custom
token output are reported when that projection is requested.

The ASCII bridge checks the complete source and preserves protected signatures.
Unmigrated subclasses may keep ASCII-based recognition, but must leave input
unchanged and return in-range progress. Explicit byte/code-point policies use
the stricter source opt-in rules. The ASCII restriction applies to this adapter,
not to existing `match()` calls.

The adapter calls recognition hooks, not an overridden public `match()` method.
Custom public entry-point behavior needs its own wrapper. Legacy projection
requires a Success or Failure match; use the normal match factories for custom
parsers. Mode checks remain active, so an ASCII input policy does not make an
incompatible grammar/source mode valid.

Results and adapters do not freeze mutable grammars, introduce an explicit effect
commit API, or promise new streaming/performance behavior. Use the supported
match factories and source delegation hooks for custom extensions. Arbitrary
numeric values embedded in manually registered callbacks are not rewritten;
coordinate conversion covers the documented Parser callback arguments.
