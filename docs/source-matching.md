# Source-based recognition

Every terminal, combinator, Ruleset and RFC import uses the same source-based
runtime. Public calls are `matchPrefix(Source, int $byteOffset = 0)` and
`parseAll(Source, int $byteOffset = 0)`, returning ParseResult. Old string matching,
ASCII bridges and source-compatibility dispatch have been removed.

Text matches full literals one recognition unit at a time. Char, Set, Range and
Any consume one code point in UTF-8 mode or one octet in byte mode. Text literals
must be valid UTF-8 in text mode; octet bounds/characters must be one byte in byte
mode. Range performs code-point ordinal comparisons for text. Case mapping uses
explicit UTF-8 in text mode and ASCII byte mapping in byte mode. Range rejects
case mappings that expand into more than one code point.

Regex matches must begin at the supplied byte position and consume a nonempty
match. Text mode enables PCRE UTF-8, rejects `\C` byte escapes and checks complete
code-point endpoints. Byte mode rejects regex UTF options. Regex execution errors
throw rather than masquerading as ordinary failures. General regexes retain
full-subject semantics and may access the whole source. For quoted literals,
`Regex::literalWindow(string)` and the facade's `literalWindow()` factory provide
bounded matching. See [file input](file-input.md) for source-memory costs.

Start/End use source byte positions. Nothing succeeds with a zero-width span;
repeating a nullable parser throws. Combinators retain byte consumption and
selected children. A failure consumes zero bytes publicly even when its internal
record has observed progress. [Diagnostics](diagnostics.md) preserve that distinction.

See [source APIs](sources.md), [consumption](consumption.md),
[custom extensions](source-and-extension-contract.md) and the runnable
[execution examples](https://github.com/vanderlee/Comprehend/blob/master/docs/examples/execution.php).
