# Cached typed semantic values

`Parser::mapValue(callable)` declares a lazy mapping from an accepted
`ParseResult` to any PHP value. `ParseResult::value()` evaluates that mapping on
its first read and caches the result for the lifetime of the recognized match.
Unmapped successful results return original raw text. Failed results return
null without evaluating the mapper; successful mappings may also return null,
so inspect `success` to distinguish those cases.

```php
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Source\Utf8Source;

$number = (new Text('42'))->token('number')->setResult('digits')
    ->mapValue(fn (ParseResult $result): int => (int) $result->result('digits'));
$result = $number->parseAll(new Utf8Source('42'));
$result->value();   // int 42, calculated once
$result->value();   // same cached value
$result->results(); // ['digits' => '42']: supported named collection
$result->rawText(); // '42'
$result->ast()->span->end; // 2, unchanged by semantic mapping
```

Scalars, arrays, null and object values are cached. Object results retain their
identity; they are not cloned or made immutable. A mapping exception is cached
and rethrown on later reads without rerunning the mapper. Recursive reads of the
same value throw `LogicException`. Syntax acceptance is already established:
mapper errors are semantic errors and do not retry alternative recognition.

The mapping callable and named result callback list are captured per
recognition. Later `mapValue()` or `setResult()` calls on a mutable parser affect
future matches, not existing results. Each new recognition gets a separate
cache. Captured external objects and references remain under the caller's
control; mapping and collection caches do not freeze those dependencies.

Named result collection remains a separate supported projection with its
existing cache. Mapping may read `result()`, `results()`, CST or AST data, but
reading those other projections does not automatically evaluate `value()`.
Default `value()` uses raw text independently of a transformed default result.
Token serialization, raw source and AST spans are unaffected by either mapping
or collection output.

Transparent wrappers such as lexeme and spacing inherit the wrapped mapping
unless explicitly mapped themselves. Other combinators and named rule wrappers
use their own mapping or default raw text; they do not implicitly evaluate child
mappings. Discarded alternatives and prefixes rejected by EOF never evaluate
their mappings. An accepted result can be wrapped by `LegacyParserAdapter` and
then `ParseResult` without losing its mapping/cache. Legacy string `match()` does
not expose the source-based typed value API.

`mapValue()` follows the existing mutable parser authoring style; recognition
snapshots isolate its lifetime. See [Recognition, validation and effect commit](lifecycle.md)
for pure guards and selected semantic validation. Prefer pure mappings; external side
effects are not rolled back by the cache.
