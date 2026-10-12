# Prefix and full-input entry points

Phase 1.5 exposes two Source-based entry points over the same recognition runtime:

| Entry point | Acceptance | Return |
|---|---|---|
| `matchPrefix(Source $source, int $byteOffset = 0)` | A prefix beginning at the supplied offset; remaining input is allowed | Canonical ParseResult |
| `parseAll(Source $source, int $byteOffset = 0)` | Recognition must end at source EOF | Canonical ParseResult |
| `parseSource(...)` | Alias for `matchPrefix(...)` | Canonical ParseResult |
| Existing `match($string, $offset)` / `matchSource(...)` | Their existing prefix contracts | Success/Failure |

Canonical entry points require an explicit ByteSource or Utf8Source. Positions
are bytes, and UTF-8 starts must be code-point boundaries. A nonzero start means
`parseAll()` consumes the entire remainder from that position, not the preceding
source bytes. Empty and failing results follow the
[canonical result contract](parse-results.md).

```php
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\Utf8Source;

$word = new Text('a');
$word->matchPrefix(new Utf8Source('ab'))->success; // true, consumes 1 byte
$word->parseAll(new Utf8Source('ab'))->success;    // false, consumes 0 bytes
$word->parseAll(new Utf8Source('a'))->success;     // true, consumes 1 byte
$word->parseAll(new Utf8Source('a '))->success;    // false: trailing space
$word->parseAll(new Utf8Source(''))->success;      // false: required literal
(new Nothing())->parseAll(new Utf8Source(''))->success; // true, empty span

$withTrailingSpaces = new Sequence($word, Repeat::star(' '));
$withTrailingSpaces->parseAll(new Utf8Source('a  '))->success; // true, 3 bytes
```

## Trivia is part of the grammar

`parseAll()` does not trim input, apply a new leading-skip rule, or consume a
spacing override after the root has finished. Leading and trailing trivia must
be recognized explicitly. Intervening spacing still follows the existing scoped
policy. A root Sequence with `spacing(...)` may accept `a b`, but still reject
`a b ` through `parseAll()` if it has no explicit trailing parser.

For example, wrap that body in `Sequence($body, Repeat::star(' '))` to accept
trailing spaces, or add a leading repeat as a separate first element. Choose
the actual comment/whitespace grammar needed; a spacing override alone does not
declare a trailing policy. Accepted trivia contributes to the canonical span
and `rawText()`. An explicit trivia child retains its ordinary token/result
projection, while automatically skipped spacing retains its existing output
and effect rules.

Completion is checked after the root recognizes, before ordinary callbacks and
assignments resolve. A successful prefix rejected by the EOF check therefore
produces no committed Parser callbacks or assignments. It yields a failed,
zero-width result at the starting position. The EOF failure occurs at the first
unconsumed byte; `farthestFailure` is the maximum of that position and any farther
observed speculative failure. Lazy projections from the rejected root are not
exposed. Definition validation/result transforms retain their existing
speculative timing; pure semantic guards remain phase 2 work.

The EOF check does not retry a successful choice or shrink a greedy repeat.
First choice between `a` and `ab` still selects `a` on `ab`, so `parseAll()` fails;
an explicit longest choice accepts this particular input. These are the same
[local selection semantics](execution.md#choice-selects-locally) as prefix parsing.

## Compatibility adapters

LegacyParserAdapter also exposes `matchPrefix()` and `parseAll()`, returning its
existing Success/Failure and Token-compatible projections. Adapter `match()`
and `__invoke()` remain prefix aliases. The adapter's chosen coordinate policy
applies to the supplied offset and scalar/token/callback lengths; canonical span
metadata remains byte-based. Its full-input check also precedes callback
resolution, including on the ASCII bridge for unmigrated subclasses.

Run `php docs/examples/execution.php` for checked prefix/full-input pairs, empty
input, and explicit trailing-trivia examples. Diagnostic expected symbols and
probe suppression remain phase 1.6 work.
