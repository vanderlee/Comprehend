# Sources and spans

All positions use source bytes. Immutable spans are half-open `[start, end)`
intervals retaining their source. `rawText()` slices those bytes exactly; no
normalization, BOM stripping or newline rewriting occurs in ByteSource/Utf8Source
or their byte/UTF-8 file counterparts.
For explicitly transcoded EncodedFileSource, the source bytes are its normalized
UTF-8 snapshot, not the original file encoding. See [File input](file-input.md).

`ByteSource` accepts arbitrary octets. `Utf8Source` validates complete UTF-8 at
construction and recognizes code points, not grapheme clusters. Both implement
Source: `mode()`, `byteLength()`, `sliceBytes(start, end)`, `span(start, end)`,
`readUnit(byteOffset)` and `isUnitBoundary(byteOffset)`. EOF is a valid boundary;
reading it returns null. Out-of-range positions throw. Source contents must stay
stable for the lifetime of referencing results and spans.

Seekable [ByteFileSource and Utf8FileSource](file-input.md) provide owned disk
snapshots, bounded read pages and incremental UTF-8 validation. They share the
same byte/span contract, with explicit close and retained-result lifetime.

```php
$source = new \Vanderlee\Comprehend\Source\Utf8Source('éa');
$source->byteLength();                       // 3
$source->codePointLength();                  // 2
$source->readUnit(0)->rawText();              // é
$source->readUnit(0)->end;                    // 2
$source->isUnitBoundary(1);                  // false
$source->byteOffsetForCodePointPosition(1);   // 2
$source->codePointPositionForByteOffset(2);   // 1
$source->span(3, 3)->rawText();               // empty string
```

Utf8Source also exposes `readCodePoint()`, `isCodePointBoundary()` and explicit
position conversions. ByteSource exposes `readByte()`. Raw byte slicing/spans
allow interior UTF-8 bytes; recognition positions and consumed endpoints do not.
`Span::cover()` includes gaps and rejects spans from different source objects.

Sources may be in memory or seekable disk snapshots. UTF-8 validation/counting scans the input;
position conversions scan prefixes. Regex matching and diagnostic indexing may
access the full input. No constant-memory streaming guarantee is made.
