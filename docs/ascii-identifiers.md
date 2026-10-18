# ASCII identifiers and keywords

These bundled helpers load automatically on first use; see
[Overview and loading](extensions.md).

## Bundled ASCII vocabulary

`Extensions\AsciiIdentifiers\AsciiIdentifiers` lives in `extensions/AsciiIdentifiers/`,
separate from the core `src/` tree. Composer autoloads the
`Vanderlee\Comprehend\Extensions` namespace from that directory. It is optional
and constructs only existing built-in parsers:

- `asciiIdentifier()` recognizes `[A-Za-z_][A-Za-z0-9_]*` greedily and returns the
  original text. Reserved words are not automatically excluded.
- `asciiKeyword(string $word)` validates the word against that same alphabet,
  recognizes it with Text, and rejects an immediately following `[A-Za-z0-9_]`.
  Invalid keyword arguments throw during authoring.

Both names also have explicit fluent registrations. They append the token to the
current expression, preserving the previous rule-fallback behavior and ordinary
sequence values. For example, `$syntax->text('@')->asciiKeyword('let')->asciiIdentifier()`
recognizes `@let name` with an appropriate spacing policy. Use `ignoreThen()` with
the standalone factory when you want only the appended token's value.

The keyword's `lexeme()` ensures its boundary probe runs before intervening spacing
is consumed. Identifier membership is explicitly case-sensitive, keeping the
alphabet ASCII even in Unicode/case-insensitive grammars. Keyword literal spelling
follows the existing Text case policy; under case-insensitive recognition Text's
normal Unicode folding still applies. Output always retains source spelling.

Both byte and validated UTF-8 sources work, with byte-based offsets. Membership
and boundaries remain ASCII: a non-ASCII character terminates an identifier, and
`asciiKeyword('if')->matchPrefix('ifé')` accepts `if`. Neither parser checks the
character preceding the requested start offset. Complete-input parsing still
rejects any remaining text. Unicode identifiers, normalization and automatic
reserved-word rejection are outside this vocabulary.

These helpers use general Regex, whose file-subject reads follow the existing
[regex limits and caching rules](file-input.md#regex-boundaries). This extension
does not promise bounded-window scans or eliminate full-subject materialization.
Values remain lazy, trees preserve syntax, and effects require explicit commit.

Run [the syntax extension example](https://github.com/vanderlee/Comprehend/blob/master/docs/examples/syntax-extensions.php) for checked examples.

