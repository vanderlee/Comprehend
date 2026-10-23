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

## Bounded scanners

Case-sensitive `readUntil()` with a literal delimiter, `readWhileSet()` and
`readWhileRange()` accelerate exact built-in byte/UTF-8 file sources using
8192-byte scan chunks. Subjects include bounded delimiter lookahead and complete
UTF-8 endpoints; they never materialize the complete file or use the regex subject
cache. Temporary subject storage stays bounded independently of file size.
The scanners retain the same byte spans, CST, diagnostics and logical work.

Finite work budgets, predicates, case-insensitive scans, custom sources/scanners,
maximum counts above 65535 and oversized generated patterns retain unit scanning.
PCRE failures resume unit scanning at the current chunk without replaying work
already charged. Snapshot ownership and explicit close behavior are unchanged.
This acceleration applies to these scanners, not to arbitrary regex patterns.
See [performance measurements](performance.md#chunked-file-scanning) for reproduction.

## Other character encodings

`EncodedFileSource(pathOrStream, encoding, pageSize = 8192)` validates an explicit
mbstring character encoding and converts a stable copy into a UTF-8 disk snapshot.
Aliases such as SJIS are accepted; unknown encodings and transfer encodings such
as Base64 are rejected. Malformed encoded input throws rather than substituting
replacement characters. There is no encoding detection. BOM interpretation
follows the selected mbstring encoding; use explicit endian encodings as needed.

All spans, byte offsets, diagnostics and `rawText()` refer to normalized UTF-8.
`inputEncoding` reports the selected canonical encoding and `originalByteLength`
reports the original file size. Original-file byte offsets are not mapped.
Borrowed stream position/ownership, snapshot stability and close semantics match
the other file sources. All source-bound spans retain the EncodedFileSource itself.

Transcoding currently validates and allocates the complete original input plus
the UTF-8 output in memory during construction, supporting stateful encodings
without unsafe chunk boundaries. Subsequent reads use bounded pages. This is
not constant-memory decoding; ParseLimits apply to parsing, not transcoding.

```php
use Vanderlee\Comprehend\Comprehend;
use Vanderlee\Comprehend\Source\EncodedFileSource;

$source = new EncodedFileSource('legacy.txt', 'Windows-1252');
$result = Comprehend::text('café')->parse($source);
// Keep the source open until all result, tree and diagnostic reads are finished.
$source->close();
```

## Regex boundaries

General Regex uses the complete original subject, preserving offsets, `^`, `$`,
lookbehind and other context-sensitive behavior. IntegerNumber uses a full-subject
regex too. Both share one lazily materialized subject per ParseSession when the
source fits `ParseLimits::maxRegexSubjectBytes` (8 MiB by default). The cache is
released with the session; ordinary results retain the source snapshot, not the
session's cached string. Explicit source closure still invalidates reads.
Only the final built-in `ByteSource` and `Utf8Source` classes skip the repeated
readability probe, because their immutable memory buffers cannot be closed.
Exact built-in file sources check readability with an empty byte slice, which
does not load page zero or evict the active read page. Custom sources, including
`ByteFileSource` subclasses, retain their boundary probe on every subject request.

Set `new ParseLimits(maxRegexSubjectBytes: 0)` to disable caching, or set a
nonnegative byte cap appropriate to the application. Sources above the cap use
uncached full-subject reads. This cap bounds retained cache storage, not the
temporary memory needed by a single regex match. Work still charges the complete
subject on every attempt, even when cached. Arbitrary regex is not bounded-memory
matching; choose literal windows when bounded subject reads are required.

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
bytes and each digit inspected during IntegerNumber conversion and range trimming.
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

Run the checked [file example](https://github.com/vanderlee/Comprehend/blob/master/docs/examples/file-input.php) with
`php docs/examples/file-input.php`.
