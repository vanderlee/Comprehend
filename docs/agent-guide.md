# Consumer guide for AI agents

Use this guide when writing application code with Comprehend. Start with the
immutable `Comprehend` facade; object parsers, Rulesets and ABNF share its typed
parser graph and recognition runtime. For changes to Comprehend itself, follow
the repository's AGENTS.md and [contribution guide](contribute.md).

## Install and parse a value

Requires PHP 8.2+, JSON and mbstring. DOM is optional for XML output.

```sh
composer require vanderlee/comprehend
composer check-platform-reqs
```

Numbers and Extraction factories load automatically on first use. Explicit
extension registration is optional. This complete example parses one assignment:

```php
<?php

declare(strict_types=1);

require 'vendor/autoload.php';

use Vanderlee\Comprehend\Comprehend as C;

$identifier = C::regex('/[A-Za-z][A-Za-z0-9_]*/A')->label('identifier');
$assignment = C::record([
    'name' => $identifier->thenIgnore('='),
    'count' => C::integerValue(1, 100, strict: true)->label('count from 1 to 100'),
])->spacing(C::text(' ')->many());

$result = $assignment->parse('workers = 12');
if ($result->success) {
    $value = $result->value(); // ['name' => 'workers', 'count' => 12]
    echo $result->rawText();  // 'workers = 12'
} else {
    echo json_encode($result->diagnostics(), JSON_THROW_ON_ERROR);
}
```

`parse()` requires complete input. `matchPrefix()` permits remaining input;
`parseAll()` also requires completion. Facade and frozen entry points accept a
string as validated UTF-8 or an explicit Source. Use `ByteSource` for arbitrary
octets. Low-level Parser entry points require Source objects. Every offset and
span is measured in bytes, including for Unicode input.

Run `php docs/examples/consumer-guide.php` in the repository for checked examples
of valid inputs, rejected inputs, JSON reporting and deferred effects.

## Choose the value operation explicitly

Ordinary recognition combinators return original text by default. They do not
generally collect child values; choose the operation that produces the value you need:

| Intent | API | Value |
| --- | --- | --- |
| Recognize a sequence | `C::sequence($a, $b)` or `$a->then($b)` | Matched text |
| Gather operand values | `C::collect($a, $b)` | Ordered list |
| Gather named fields in insertion order | `C::record(['a' => $a, 'b' => $b])` | Keyed array |
| Recognize an alternative | `C::choice($a, $b)` | Matched text |
| Forward an alternative's value | `C::valueChoice($a, $b)` | Selected value |
| Repeat syntax | `$item->many()` | Matched text |
| Repeat values | `$item->manyValues()` or `$item->someValues()` | List of values |
| Default an absent field | `$item->optionalValue(default: null)` | Item value or default |
| Parse a delimited value | `$item->between('[', ']')` | Item value |
| Keep the left or right value | `$a->thenIgnore($b)` or `$a->ignoreThen($b)` | Selected operand's value |
| Parse a separated list | `$item->separatedBy(',')` | List of item values |
| Transform the current value | `$item->map($callback)` | Callback result |
| Replace projection using original text | `$item->mapText($callback)` | Callback result |

Terminal factories chained onto an expression append syntax. For example,
`$a->text(';')` and `$a->record($fields)` form ordinary sequences whose default
value is text. Use `thenIgnore(';')` to keep `$a`'s value, or start with
`C::record($fields)` to obtain the record. `named()` names syntax nodes; it does
not create a record field. `rawText()` always returns the matched source bytes.

`map()` transforms the existing value; `mapText()` and `mapValue()` replace the
projection. Mapping values and exceptions are lazy and cached per recognition.
See [application values](application-values.md) and [semantic values](semantic-values.md).

## Handle spacing and grammar selection

`spacing($trivia)` recognizes intervening trivia, including between zero-width
items. It does not trim leading or trailing text. Recognize boundary trivia
explicitly; use `lexeme()` to suppress automatic spacing inside a subtree.
The assignment above accepts `workers=12` and `workers = 12`, but rejects
` workers=12`, `workers=12 `, `workers=?` and `workers=12!`.

Choice selects the first success by default. Repetition is greedy. A later failure
does not retry an earlier successful choice or shrink a repetition. Left recursion
and nullable repeated items are unsupported. Use explicit named Ruleset
`define()`, `rule()` and `root()` operations for recursive grammars.
`freeze()` resolves references, checks known graph safety and retains an independent
snapshot; retain it for repeated parsing. See [grammar authoring](grammar-authoring.md),
[execution](execution.md) and [spacing](trivia.md).

## Report syntax and validate meaning separately

Check `success` for recognition and `hasRecovery()` for recovered syntax.
`diagnostics()` returns selected recovery errors and, on failure, the terminal
diagnostic. A successful result may retain an observed `diagnostic`; its presence
alone does not mean rejection. Use `label()` for expected input, `diagnosticRule()`
for rule-path names, and `named()` for CST/AST names.

```php
$failed = $assignment->parse('workers = ?');
echo $failed->diagnostic->toJson(includeLocation: true, includeExcerpt: true);
```

Default diagnostic JSON contains `byteOffset`, `expected` and `rulePaths` without
reading source bytes. Source details are opt-in; locations use one-based line/column
and excerpts declare `utf8` or `base64` encoding. See the
[diagnostic schema](diagnostics.md#array-and-json-output).

Use `validateSemantic()` to attach semantic checks and explicit
`$result->validate()` to obtain a serializable ValidationResult. Recognition
success and `value()` do not establish semantic validity. Failed recognition
returns null from `value()`; successful values can also be null, so inspect
`success` rather than testing value truthiness.

Keep recognition and guards pure, and application effects in `onCommit()`.
Only explicit `commit()` runs selected effects, after successful validation;
repeated commits do not rerun completed effects. Value access and diagnostic
serialization do not validate or commit. See [validation](validation.md) and
[lifecycle](lifecycle.md).

## Find supported APIs and verify callers

Consult the [API reference](reference.md) and the relevant extension page for
signatures. `C::syntax()->names()` includes discovered factory names;
`C::syntaxNames()` lists reserved built-in names. Available magic authoring calls
are declared with PHPDoc; use those declarations and the docs rather than guessing
method names. Removed matching APIs, magic Ruleset lookup and Token output models
are listed in the [canonical removal inventory](canonical-runtime.md).

PHPDoc generics carry value types through mapping and parsing without a PHPStan
extension. Some collection operations conservatively return mixed values; see
[static value types](semantic-values.md#static-value-types) for the limits.
In your application, test accepted and rejected inputs, exact extracted values,
failure offsets and expectations, and any validation or commit behavior you use.
