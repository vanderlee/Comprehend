# Parse application values

This tutorial uses the automatically loaded [Extraction expansion](extraction.md).
`C::syntax()` and global calls work without registration; `Extraction::syntax()`
remains supported in the examples below.

Build a parser that returns a domain object directly. This tutorial uses a small
job format with an optional retry setting and nested lists of batch IDs:

```text
report retry=off [[1, 2], [], [3]]
report [[1], [2, 3]]
```

The first job disables retries. The second omits the setting and defaults to three
retries. Empty outer and inner lists are accepted. IDs range from 0 to 999, and
numeric retry counts from 0 to 9. The format has no quoted names or comments.

Run the complete, checked example:

```sh
php docs/examples/application-values.php
```

The [grammar source](https://github.com/vanderlee/Comprehend/blob/master/docs/examples/application-values-grammar.php)
contains the domain class and `jobParser()` factory used by the regression tests.
The steps below build the same grammar with the `Comprehend` facade.

## Start with the domain object

```php
final class Job
{
    public readonly string $name;
    public readonly int|bool $retries;
    /** @var list<list<int>> */
    public readonly array $batches;

    /** @param list<list<int>> $batches */
    public function __construct(string $name, int|bool $retries, array $batches)
    {
        $this->name = $name;
        $this->retries = $retries;
        $this->batches = $batches;
    }
}
```

The grammar supplies either an integer retry count or `false` for disabled
retries. Application values do not require naming AST nodes or traversing syntax.

## Recognize text and convert numbers

```php
use Vanderlee\Comprehend\Extensions\Extraction\Extraction;

$name = Extraction::syntax()->regex('/[A-Za-z][A-Za-z0-9_]*/A');
$number = Extraction::syntax()->integer(0, 999)->mapText('intval');
```

An unmapped parser returns its matched text. `mapText()` converts the number's
source text to an integer. Conversion is deferred until its value is needed.
The regex is anchored at the parser's current position; names use ASCII letters,
digits and underscores and must begin with a letter.

## Preserve values from alternatives

```php
use Vanderlee\Comprehend\Extensions\Extraction\Extraction;

$retryValue = Extraction::syntax()->valueChoice(
    Extraction::syntax()->integer(0, 9)->mapText('intval'),
    Extraction::syntax()->text('off')->map(static fn(string $text): bool => false),
);
$retries = Extraction::syntax()->text('retry=')->ignoreThen($retryValue)->optionalValue(default: 3);
```

`valueChoice()` forwards the successful alternative's value. Ordinary `choice()`
returns its matched text by default. `ignoreThen()` recognizes the literal prefix
but selects the setting's value. `optionalValue()` supplies 3 only when the whole
setting is absent; a present zero or false remains unchanged. A successful child
whose value is null would likewise preserve null.

An incomplete setting is not repaired. When used inside this job grammar,
`retry=unknown` cannot match the following batch list, so complete parsing fails.
Optional parsing itself can accept an empty prefix when its child fails; it does
not commit to a field just because part of its prefix matched.

## Build nested lists without source traversal

```php
$batch = $number->separatedBy(',')->between('[', ']');
$batches = $batch->separatedBy(',')->between('[', ']');
```

`separatedBy()` returns a list of item values. `between()` selects that list while
recognizing both delimiters. Applying them twice preserves the nesting:
`[[1, 2], [], [3]]` becomes the same shape of PHP arrays containing integers.
The default minimum is zero, and trailing separators are rejected.

For adjacent items without separators, use `manyValues()` or `someValues()`.
Use `repeatValues(min: ..., max: ...)` for explicit bounds. These return lists of
item values too; ordinary `many()` and `some()` retain raw-text defaults.

## Collect fields and construct the object

```php
use Vanderlee\Comprehend\Extensions\Extraction\Extraction;

$job = Extraction::syntax()->collect($name, $retries, $batches)
    ->map(static fn(array $parts): Job => new Job($parts[0], $parts[1], $parts[2]))
    ->spacing(Extraction::syntax()->set(" \t\r\n")->many());
```

`collect()` supplies the direct operand values in order: name, retry setting,
and nested lists. `map()` transforms that array into a Job. It composes with the
existing value projection, whereas `mapText()` would receive the entire matched
string and `mapValue()` would receive a ParseResult.

Spacing applies between grammar items, including list separators. It does not
add entries to collected values or lists. This grammar accepts spaces, tabs and
line breaks between items, but does not trim leading or trailing whitespace.
The literal `retry=` stays contiguous. Recognize document boundary trivia
explicitly if your format allows it; see [spacing and trivia](trivia.md).

## Parse, check success, then read the value

```php
$grammar = $job->freeze();
$input = 'report retry=off [[1, 2], [], [3]]';
$result = $grammar->parse($input);
if (!$result->success) {
    throw new RuntimeException($result->diagnostic?->message() ?? 'Invalid job');
}

$value = $result->value();
$value->name;    // 'report'
$value->retries; // false
$value->batches; // [[1, 2], [], [3]]
$result->rawText(); // original input, including spaces and punctuation
```

`parse()` requires complete input. Malformed fields, missing delimiters and
trailing input fail. Check `success` instead of using the value as a success flag:
valid parsers can return false or null. Structured failures describe their byte
offset and expectations; see [diagnostics](diagnostics.md).

Freezing prepares a reusable grammar; each parse still owns its value caches.
Constructors and mapping callbacks are lazy: parsing alone does not construct the
Job. Repeated `value()` calls return the same cached object for that result;
another parse constructs its own object. A mapping exception is also cached and
is thrown when the value is read. Recognition success does not guarantee that
application mapping code will complete successfully.

The syntax tree and byte spans retain the source independently of the domain
object. Keep mappings pure. Value access does not run commit effects; use
`onCommit()` and explicit `commit()` for application effects. See
[semantic values](semantic-values.md) and [lifecycle](lifecycle.md) for details.

For static analysis, the example's factory declares `@return C<Job>`. With the
[PHPDoc signatures](semantic-values.md#static-value-types), its mapping callbacks
are checked and `jobParser()->freeze()->parse($input)->value()`
has type `Job|null`. The nullable result accounts for recognition failure; checking
the boolean success property alone does not narrow that value type. Collected tuple
positions are conservatively `mixed` in static analysis.
