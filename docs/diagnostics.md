# Structured source diagnostics

Canonical `matchPrefix()`, `parseSource()` and `parseAll()` results expose a nullable
`diagnostic`. Source-bound matches and legacy coordinate adapters expose the same
metadata. Legacy string `match()` has no source diagnostic.

```php
$result = (new Text('éa'))->parseAll(new Utf8Source('éb'));
$result->diagnostic->byteOffset; // 2
$result->diagnostic->expected;   // ['"éa"']
$result->diagnostic->location(); // ['line' => 1, 'column' => 2]
$result->diagnostic->excerpt();  // 'éb'
$result->diagnostic->message();  // Expected "éa" at line 1, column 2 (byte 2)
```

Recognition records immutable facts: `source`, `byteOffset`, `expected` and
`rulePaths`. Only expectations at the farthest retained byte position appear;
ties merge and deduplicate expectations and paths in encounter order. Terminal
labels use their existing grammar notation. Paths contain parser class names,
with named rules substituted where available. Custom parsers can supply a label
using `diagnosticRule('name')` or override protected `diagnosticName()` and
`diagnosticExpectation()`. Wrapper failures do not add generic expectations when
their children already explain the failure. Failed results still consume zero
bytes, independently of the diagnostic position.

The reporting policy is:

- Failed choice alternatives remain relevant, even if another alternative
  succeeds. A successful prefix can therefore carry a diagnostic farther than
  its consumed span. This preserves information about incomplete alternatives;
  it does not make that prefix fail.
- Optional and repeated children discard their final failed attempt once the
  minimum count is satisfied. A failed attempt needed to reach the minimum is
  retained, including failures of required spacing before that attempt.
- Negative lookahead discards all diagnostics from its child. If the child
  succeeds, `Not` reports its own failure at the lookahead start, rather than at
  the child's end. `Except` likewise suppresses its exclusion probe and reports
  an exclusion failure at the start; failures of its required matching child
  remain relevant. `All` retains failures of required children.
- Successful spacing discards speculative diagnostics inside the spacer;
  failed required spacing contributes its expectations and caller/rule path.
- `parseAll()` contributes `end` at the unconsumed suffix. A farther relevant
  choice failure takes precedence; tied expectations merge. EOF checks do not
  retry recognition or resolve rejected effects.

`farthestFailure` mirrors the retained diagnostic's byte position. It is null
when no relevant failure remains, including normal repetition termination.

Line starts are indexed lazily on the first location or excerpt request and
cached in the diagnostic. Locations are one-based; CRLF counts as one newline,
and lone CR and LF also start new lines. UTF-8 columns count Unicode code points,
not display cells; tabs count as one. Byte-source columns count bytes. Excerpts
return the current raw line without its newline, including an empty line at EOF
after a newline. Byte excerpts can contain arbitrary bytes, so callers must
escape them appropriately when displaying them. Diagnostic positions always
remain canonical bytes, including on coordinate adapters.
