# Seekable file input and runtime limits

`ByteFileSource` and `Utf8FileSource` implement the same Source interface as
in-memory sources. They accept a readable path or borrowed seekable stream:

```php
use Vanderlee\Comprehend\Source\Utf8FileSource;
use Vanderlee\Comprehend\Parser\Terminal\Regex;
use Vanderlee\Comprehend\Runtime\ParseLimits;

$source = new Utf8FileSource('input.txt');
$result = Regex::literalWindow('hello')->matchPrefix($source,
    limits: new ParseLimits(maxDepth: 100, maxWork: 100000));
$result->rawText();
// Close only after finishing every result, span, diagnostic and projection read.
$source->close();
```

## Ownership and stability

Construction copies input to a private temporary disk snapshot in bounded chunks.
A path's input handle is opened/closed by the source. A borrowed stream remains
open and its position is restored, even if validation fails. Input must be
seekable/readable and stable during copying; nonseekable streams are unsupported.
After construction, edits or closure of the original cannot alter source spans.

The source owns its snapshot until explicit idempotent `close()` or destruction
after the last source/result/span reference is released. Sources cannot be
cloned. Closing invalidates data reads, including retained result raw text,
serialization, AST text, diagnostics and code-point conversion; cached metadata
such as byte length remains available. Releasing a local source variable does
not close a snapshot still retained by a result. Do not close it before committing
effects that read source text. Temporary disk use is proportional to input size.

Random byte slicing uses one read page (8192 bytes by default; minimum 4).
`sliceBytes()` returns exactly the requested interval and can allocate a large
string if explicitly asked. Bytes are never normalized or stripped. Byte sources
accept arbitrary octets; UTF-8 sources validate incrementally using bounded
chunks and continuation state, rejecting overlong sequences, surrogates,
out-of-range code points and truncation. Reads crossing page/validation boundaries
preserve complete code points. UTF-8 position conversion scans units without a
full code-point index, trading bounded auxiliary storage for linear scan time.

## Regex boundaries

General Regex uses the complete original subject, preserving offsets, `^`, `$`,
lookbehind and other context-sensitive behavior. Integer currently uses a
full-subject regex too. On a file source these operations allocate the subject;
they are not bounded-memory matching and repeated regex calls can reread it.

`Regex::literalWindow(string)` is an explicit opt-in for quoted literals only.
It escapes metacharacters, anchors at the supplied start and reads a proven
finite window: literal byte length in byte mode, at most four bytes per literal
code point in UTF-8 mode. The latter permits Unicode caseless variants with
different byte lengths. Window ends stay on code-point boundaries. It preserves
literal prefix acceptance at nonzero offsets without pretending that arbitrary
regex patterns can be truncated safely. There is no generic window setting;
unsupported patterns keep the complete-subject path.

## Per-session budgets

Parser and FrozenGrammar `matchPrefix()`/`parseAll()` accept an optional third
`ParseLimits`, also available as named `limits:`. `maxDepth` and `maxWork` are
positive integers or null; null disables that limit. Each public parse creates
fresh counters. Depth counts simultaneously active parser recognitions. Work
counts parser entries, source-unit requests through ParseSession, regex subject
bytes and each digit inspected during Integer conversion and range trimming.
Failed/speculative attempts count;
diagnostic suppression never refunds work. Full-subject regex work is charged
before reading the subject, so a small budget can reject a large allocation.

Exhaustion throws ParseLimitException with `limit`, `maximum`, canonical
`byteOffset` and `rulePath`. It is distinct from syntax rejection and produces no
committable root. Parser scopes/active entries restore on exceptions; work is not
reset inside a reused session. Limits do not bound source construction, semantic
callbacks, output projection or arbitrary native PCRE CPU work. PCRE's own
limits still apply. Custom recognizers should charge long loops through
`consumeWork()` and use `readSourceUnit()`; uncooperative user PHP code cannot be
preempted. Limits are cooperative execution bounds, not wall-clock deadlines.

`ParseMetrics` is optional bounded profiling via an explicitly supplied
ParseSession. It records entries and repeated parser/byte positions, keeping at
most 4096 positions by default. Scope context is intentionally omitted: repeats
are investigation candidates, not valid memoization keys. No recognition-result
cache is introduced. See [Performance measurements](performance.md) for the
recorded memoization decision and retention tradeoffs.

Run the checked [file example](examples/file-input.php) with
`php docs/examples/file-input.php`.
