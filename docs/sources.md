# In-memory sources and spans

The phase 1.2 source API remains independent of legacy `Parser::match()`.
Phase 1.3 adds explicit [source-based recognition](source-matching.md) using
`matchSource()`; legacy string recognition is unchanged. The
[source and extension contract](source-and-extension-contract.md) defines the
coordinate rules; [execution limitations](execution.md#current-unicode-limitation)
still apply to legacy recognition.

All source positions and span lengths use original **bytes**. Spans are immutable
half-open intervals `[start, end)` retaining their source; `rawText()` slices
exactly those bytes. Invalid bounds throw `InvalidArgumentException`.

```php
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;

$text = new Utf8Source("\u{00E9}a");
$text->byteLength();                              // 3
$text->codePointLength();                         // 2
$letter = $text->readCodePoint(0);
$letter->rawText();                               // é
$letter->start;                                   // 0
$letter->end;                                     // 2, next byte boundary
$letter->byteLength();                            // 2
$text->readCodePoint(3);                          // null at EOF
$text->span(3, 3)->rawText();                     // empty string at EOF
$text->byteOffsetForCodePointPosition(1);         // 2
$text->codePointPositionForByteOffset(2);          // 1
$text->isCodePointBoundary(1);                    // false
$text->sliceBytes(1, 2);                          // byte a9, not valid UTF-8 alone
// $text->readCodePoint(1);                       // throws: interior byte

$bytes = new ByteSource("\xFF\0");
$bytes->readByte(0)->rawText();                    // byte ff
$bytes->readByte(1)->end;                          // 2
$bytes->readByte(2);                              // null at EOF
```

## API and modes

| Type | Operations |
|---|---|
| `Source` | `mode(): SourceMode`, `byteLength(): int`, `sliceBytes(int $start, int $end): string`, `span(int $start, int $end): Span` |
| `ByteSource` | Accepts arbitrary octets; `readByte(int $byteOffset): ?Span` reads one octet. Mode is `SourceMode::BYTES`. |
| `Utf8Source` | Validates the complete input; `readCodePoint(int $byteOffset): ?Span` reads one code point. Mode is `SourceMode::UTF8`. |
| UTF-8 conversions | `codePointLength(): int`, `byteOffsetForCodePointPosition(int $position): int`, `codePointPositionForByteOffset(int $byteOffset): int`, `isCodePointBoundary(int $byteOffset): bool` |
| `Span` | Read-only `source`, `start`, `end`; `byteLength(): int`, `rawText(): string`, `cover(Span $other): Span` |

Byte mode never decodes input. UTF-8 mode rejects malformed/truncated encodings
at construction and uses explicit UTF-8 encoding in all mbstring operations.
Neither mode normalizes, strips a BOM, or rewrites newlines. UTF-8 character
positions count code points, including combining marks, not grapheme clusters.

Reads and conversions reject negative or beyond-EOF positions. Text reads and
byte-to-code-point conversion also reject interior bytes.
`isCodePointBoundary()` returns false for an in-range interior byte and throws
for an out-of-range position. EOF is a boundary even in an empty source.
Raw slicing and spans allow interior bytes and empty intervals; these are byte
operations and do not promise valid UTF-8 output.

`cover()` returns the smallest interval containing both spans, including gaps.
It rejects spans from different source objects, even when their text is equal.
The original spans are unchanged. Source implementations must keep content
stable for the lifetime of their referencing spans.

In-memory sources retain their original string. UTF-8 construction validates
and counts the complete input; position conversions scan the relevant prefix
using explicit encoding and do not cache a position map. No file input,
streaming, line/column index, or legacy coordinate adapter is provided by the
source layer itself.
