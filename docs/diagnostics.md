# Structured diagnostics

ParseResult separates consumed byte length from observed failure position.
`diagnostic` contains Source, `byteOffset`, `expected` labels and `rulePaths`.
Tied alternative failures retain distinct expected labels and paths. Named rules
add readable names. Text literal diagnostics use quoted literals; custom parsers
can override protected `diagnosticName()` and `diagnosticExpectation()`.

## Array and JSON output

Diagnostic implements JsonSerializable and exposes `toArray()` and `toJson()`.
The default payload contains only `byteOffset`, `expected` and `rulePaths`.
It excludes the Source object and does not read source bytes or index lines:

```php
use Vanderlee\Comprehend\Comprehend as C;

$status = C::text('ok')->label('status')->diagnosticRule('status');
$result = $status->parse('?');
echo json_encode($result->diagnostics(), JSON_THROW_ON_ERROR);
// [{"byteOffset":0,"expected":["status"],"rulePaths":[["status"]]}]
```

Use `ParseResult::diagnostics()` for selected recovery errors and terminal failure.
A successful result can retain an observed `diagnostic` without being rejected;
inspect `success` and `hasRecovery()` separately. Semantic issues are returned by
explicit `validate()` and have their own [validation serialization](validation.md).

Opt into source details with named arguments on `toArray()` or `toJson()`:

```php
echo $result->diagnostic->toJson(includeLocation: true, includeExcerpt: true);
// {"byteOffset":0,"expected":["status"],"rulePaths":[["status"]],
//  "location":{"line":1,"column":1},"excerpt":"?","excerptEncoding":"utf8"}
```

Both options default to false and can be used independently. `includeLocation`
adds `location` with one-based `line` and `column`. `includeExcerpt` adds `excerpt`
and `excerptEncoding`: valid UTF-8 is emitted directly as `utf8`, and arbitrary
non-UTF-8 bytes are base64 with encoding `base64`. Empty excerpts use `utf8`.
This preserves the original bytes, including NUL. Offsets remain bytes; UTF-8
columns count code points and byte-source columns count bytes. Either option may
index the entire source, including a file source. Plain `json_encode($diagnostic)`
always uses the default compact payload, even after source details were requested.

Serialization does not evaluate semantic values, run validation or commit effects.
`toJson()` uses JSON_THROW_ON_ERROR; invalid UTF-8 in expectation labels or rule
names throws JsonException rather than replacing metadata. Only excerpts receive
the explicit base64 encoding. Run the
[consumer guide example](https://github.com/vanderlee/Comprehend/blob/master/docs/examples/consumer-guide.php)
for checked reporting and lifecycle behavior.

## Expectation labels

Use `label(string $label)` to describe what a parser expects:

```php
$identifier = C::regex('/[A-Za-z][A-Za-z0-9_]*/A')->label('identifier');
$result = $identifier->parse('?');
// $result->diagnostic->expected === ['identifier']
```

Import `Vanderlee\Comprehend\Comprehend as C`. Labels are available on fluent
expressions, Syntax-created expressions and low-level Parser objects. Like other
low-level configuration, `Parser::label()` mutates that builder; fluent calls
detach the expression. Blank labels are rejected.

When a labelled parser fails at its entry byte position, its own low-level
expectations are replaced by the label. Earlier alternatives' expectations at
that position remain, and labels from tied alternatives accumulate. An outer
label replaces nested labels when the entire labelled parser fails at that same
boundary. The replacement's rule path ends at the labelled parser, retaining
its diagnostic rule name; the label text is not inserted into the path.

Failures farther into the input retain their detailed expectations and byte
positions, including deferred malformed-list diagnostics. For example,
`C::text('hello')->label('greeting')->parse('hel?')` still expects `"hello"` at
byte 3. Lookahead and exclusion can fail at their entry position despite observing
child progress; such entry failures can be labelled. Labels do not change source
consumption or the selection of alternatives.

Successful parsers do not replace observed failure details or add a new
expectation. Optional/repetition/lookahead rollback discards labelled probes in
the same way as ordinary probes. A later complete-input EOF failure is not
relabeled. Guard rejection follows its diagnostic position; semantic validation
is unchanged. Labelled patterns retain their normal recognition path when an
optimization would otherwise bypass relabelling.

`label()` is separate from `diagnosticRule()`, which names rule-path entries, and
`named()`, which names CST/AST nodes. It creates no wrapper node and changes no
tree names, semantic values or commit effects. See the runnable
[labels example](examples/labels.php).

## Locations and retained observations

`location()` lazily indexes CRLF, lone CR and LF and returns one-based line/column.
UTF-8 columns count code points; byte-source columns count bytes. `excerpt()`
returns the original current line and `message()` formats expectations/location.
Indexing may read the whole source. Raw byte excerpts may not be valid UTF-8.

Within a parse session, unchanged failure details reuse the same Diagnostic
snapshot. New expectations, paths or failure positions produce a new snapshot;
retained results keep their original details. Speculative rollback invalidates
the cache when those details change, independently of the restored revision
counter. Ordinary and deferred-list diagnostics remain distinct until a failed
enclosing parse needs their merged details.

Negative-lookahead children, optional probes, successful exclusion probes and
successful repetition termination do not pollute expectations. Required failures,
guard rejection and relevant tied alternatives retain their details. Full-input
failure adds EOF at the accepted endpoint unless an observed failure is farther.
Successful results may retain useful observed failures; the presence of a
diagnostic alone does not mean the selected syntax failed.

Separated lists keep abandoned item/separator failure details separately from
successful termination diagnostics. If the enclosing recognition or complete-input
check fails, the farthest of these details and the ordinary failure is reported;
tied expectations and rule paths are combined. For example, `report\n[[1,\n?]]`
in the application-value comparison now reports the invalid item on line 3,
column 1 rather than the opening bracket on line 2. Successful prefixes and
complete lists do not expose these termination probes. Optional, repetition,
lookahead and recovery probe checkpoints also restore these deferred details.

Explicit [error recovery](recovery.md) retains each recovered region's original
failure separately. Use `diagnostics()` for that selected list and `hasRecovery()`
to distinguish recovered acceptance from ordinary acceptance. Synchronization
probe failures do not contribute expectations.
