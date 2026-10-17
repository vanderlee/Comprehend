# Structured validation

Attach `validateSemantic()` to a parser or use a Definition validator. Validation
runs lazily on accepted syntax, independently of reading semantic values.
Validators receive a `ParseResult` and may return:

- `true` or `[]` to accept;
- `false` for a default `validation.failed` issue;
- a string for a `validation.failed` issue with that message;
- one `Result\ValidationIssue`, or a list of them.

```php
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Result\ValidationIssue;

$age = $syntax->integer()->validateSemantic(
    static fn(ParseResult $result): bool|ValidationIssue => (int) $result->rawText() <= 130
        ? true
        : new ValidationIssue('age.range', 'Age exceeds 130', $result->span, ['maximum' => 130]),
);
$report = $age->parse('150')->validate();
foreach ($report->issues as $issue) {
    echo $issue->code . ': ' . $issue->message;
}
```

An issue has immutable `code`, `message`, `span` and `context` properties. Codes
must be nonblank. Spans use byte offsets and must belong to the exact recognized
Source object; they may identify another field outside the validating parser's
span. Context accepts only finite JSON-compatible scalars, null and nested arrays.
It is copied recursively, including caller-owned references. Objects, resources,
recursive arrays and invalid UTF-8 context are rejected.

Children and accepted trivia contribute issues before enclosing validators.
Discarded alternatives contribute nothing. Validation does not retry a selected
alternative. Reports, issue objects and thrown validation exceptions are cached.
Malformed return values, associative issue arrays and foreign-source issues throw
`UnexpectedValueException`. Any issue blocks `commit()` and all selected effects.

`ValidationResult` derives `valid` from an empty `issues` list. Its constructor is
`new ValidationResult($issues)`; the former boolean constructor and `errors`
property are removed. Replace message-only reads with
`array_column($report->issues, 'message')`.

`toArray()`, `toJson()` and `JsonSerializable` expose only data:

```json
{"valid":false,"issues":[{"code":"age.range","message":"Age exceeds 130","span":{"start":0,"end":3},"context":{"maximum":130}}]}
```

Issue serialization includes byte start/end coordinates, never the Source object.
JSON encoding throws for non-UTF-8 message text, as with tree serialization.

Failed syntax produces `syntax.rejected` at the diagnostic byte offset. Explicit
recovery produces `syntax.recovered` issues over recovered regions, skips semantic
validators and refuses commit. There are no warning severities: every issue means
validation failed. See [lifecycle](lifecycle.md) and the runnable
[validation example](examples/validation.php).
