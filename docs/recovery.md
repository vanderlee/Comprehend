# Error recovery

Recovery lets a grammar continue through invalid input while retaining errors and
original source bytes. It is an optional extension, enabled explicitly at a grammar
boundary. Ordinary parsers remain strict.

Recovery parser implementations live in `Extensions\Recovery\Parser`; the strategy
context remains `Extensions\Recovery\RecoveryContext`.

```php
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Recovery;

$syntax = C::syntax()->extend(new Recovery());

$statement = $syntax->text('ok;')->recoverUntil(';', consumeBoundary: true);
$result = $statement->some()->parse('ok;bad;wrong;ok;');

$result->success;          // true: the whole input was accepted
$result->hasRecovery();    // true: acceptance required recovery
count($result->recoveries()); // 2
$result->validate()->valid;   // false
$result->commit();            // false; no effects execute

foreach ($result->diagnostics() as $diagnostic) {
    echo $diagnostic->message(), PHP_EOL;
}
```

## Authoring

Install `Extensions\Recovery` with `Syntax::extend()` before authoring recovery.
The extension registers `recoverUntil`, `recoverDelimited`, `recoverItem` and
`recoverWith` as both rules and fluents. Fluent calls require an expression
constructed through the extended vocabulary. Use `$syntax->recoverUntil($parser, ...)`
to wrap an existing expression from another vocabulary.

The parser classes, `RecoveryContext` and internal `BoundedItem` now live under
`Vanderlee\Comprehend\Extensions\Recovery` in `extensions/Recovery/`. The former
core class names and global `C::recover*()` factories are removed. Update imports
and install the extension when migrating. `Result\Recovery`, selected diagnostics,
and validation/commit protection remain shared core result contracts; the core
does not depend on the extension or register it automatically.

Both static and fluent factories construct the same typed parser:

```php
$syntax->recoverUntil($parser, $boundary, consumeBoundary: false, allowEof: false, maxSkippedBytes: null);
$parser->recoverUntil($boundary, consumeBoundary: false, allowEof: false, maxSkippedBytes: null);
```

The object API is `new Extensions\Recovery\RecoverUntil(Parser $parser, Parser
$boundary, bool $consumeBoundary = false, bool $allowEof = false,
?int $maxSkippedBytes = null)`.

The operand is attempted first. If it succeeds, its value, concrete tree and
lifecycle are preserved. Otherwise the parser searches from the operand's start
for the first successful boundary, advancing by source units: code points for
UTF-8 sources and octets for byte sources. Coordinates and limits remain in bytes.

By default the boundary remains for the enclosing grammar. Use this for a closing
brace owned by a block. Set `consumeBoundary: true` to include a statement's
semicolon in the recovered region. Boundary syntax is only a probe, even when
consumed: its mappings, validators, collections and effects are not retained.
Recognition guards still run and must remain pure.

Without a boundary the original attempt fails, unless `allowEof: true` explicitly
permits recovery through EOF. A repair must consume at least one source unit;
an immediately encountered retained boundary cannot produce a zero-width repair.
Boundaries must be non-nullable, checked during freezing and at runtime.

`maxSkippedBytes` bounds the entire recovered region, including a consumed
boundary; exceeding it returns the original failure. Existing `ParseLimits`
also bound parser entries, synchronization probes and scanning. Budget exceptions
propagate normally rather than becoming recoverable syntax errors.

## Results, trees and semantics

Each immutable `Result\Recovery` contains a source-bound `span` and the original
failed operand's `diagnostic`. `ParseResult::recoveries()` reports records from
selected matches only. Discarded choices, failed tails and lookahead cannot leak
recovery records. `diagnostics()` returns those recovery diagnostics on accepted
results, or the ordinary terminal diagnostic on failure. The existing singular
`diagnostic` continues to describe ordinary recognition progress.

The CST preserves each skipped region as a child tagged `error` and `recovered`.
Its raw text includes any consumed boundary and round-trips without fabricated
tokens. Tree serialization retains these tags using the existing schema. No
partial structure or deferred work from the failed operand is retained.

The default value of a directly recovered parser is its `Recovery` record. It is
not a fabricated value of the operand's expected type. Outer value projections
keep their usual behavior; typed reducers processing partial structures must
handle recovery records explicitly. Reading values remains lazy and cached.

Any accepted result containing recovery fails validation before running semantic
validators and refuses commit before executing effects, including effects on
otherwise valid siblings. A valid subtree accessed independently retains its own
normal lifecycle. Failed whole-document results do not expose partial trees or
recovery records from discarded successful prefixes.

## Choosing boundaries

Wrap the complete choice of valid statements, rather than the first alternative:

```php
$statement = $syntax->choice($assignment, $declaration)->recoverUntil(';', true);
```

A recovered alternative counts as accepted under existing choice policies, so
placing it before other valid alternatives can prevent those alternatives from
being tried.

Synchronization is grammar-directed. A literal semicolon probe does not understand
strings, comments or delimiter nesting, and may stop inside them. Choose explicit
statement boundaries appropriate to the language, or use the tools below.
Missing-token insertion and deletion/retry are not provided.

## Delimited recovery

`recoverDelimited()` owns both delimiters. Pass the parser for the interior,
without applying `between()` first:

```php
$ignore = $syntax->choice($quotedString, $comment);
$group = $syntax->recoverDelimited($expression, '(', ')', ignore: $ignore);
```

On valid input this behaves like `$expression->between('(', ')')`, including
scoped spacing, selected syntax, effects and the interior's typed value. On
failure it requires the opening delimiter at the starting position, counts nested
occurrences of the same delimiter pair, and consumes through its matching close.
For `(broken(nested) ")")`, the quoted close is ignored when the supplied ignore
parser recognizes the complete string. The whole malformed group becomes one
recovery region. Missing opening delimiters never initiate this strategy.

The scanner tries `ignore`, then `close`, then `open` at each position; ambiguous
overlapping definitions follow that priority. It does not infer string syntax,
comments, escaping, or other delimiter pairs. Supply a non-nullable ignore parser
for complete regions that must be skipped atomically. An unterminated ignored
region is not recognized and its content is scanned normally.

Signatures, available statically or fluently:

```php
$syntax->recoverDelimited($parser, $open, $close, ignore: null, allowEof: false, maxSkippedBytes: null);
$parser->recoverDelimited($open, $close, ignore: null, allowEof: false, maxSkippedBytes: null);
```

The object constructor uses Parser operands in the same order. `allowEof: true`
permits a missing close only after an opening delimiter has been found. The byte
limit covers the entire recovered group, including its delimiters. Opening,
closing and ignore probes must all consume input when they succeed.

## Item recovery

`recoverItem()` constructs a separated list, not a single item wrapper. Its closing
delimiter is a synchronization boundary left for the enclosing grammar:

```php
$values = $syntax->regex('/[0-9]+/A')->mapText('intval')
    ->recoverItem(separator: ',', close: ']')
    ->between('[', ']');

$result = $values->spacing($syntax->text(' ')->many())->parse('[10, ???, 30]');
// value(): [10, Recovery for "???", 30]
```

A valid item must end before a separator or close, allowing scoped intervening
trivia. A valid prefix followed by junk, such as `12oops`, is recovered as one
malformed item; it does not silently become `12`. Item values, separators and
accepted trivia retain the ordinary separated-list contracts. The default value
is a lazy array of valid item values and `Recovery` records.

An immediately encountered separator or close does not create a missing item.
`[]` is an empty list, `[1,,3]` fails, and `[1,]` is accepted only when
`allowTrailing: true`. Missing closing delimiters are not inserted. A malformed
nonempty item counts toward `min` and `max`; recovery can neither exceed the item
bound nor manufacture a required item at EOF.

```php
$syntax->recoverItem($item, $separator, $close, min: 0, max: null,
    allowTrailing: false, ignore: null, maxSkippedBytes: null);
$item->recoverItem($separator, $close, min: 0, max: null,
    allowTrailing: false, ignore: null, maxSkippedBytes: null);
```

The object constructor takes Parser operands in the same order. `ignore` skips
complete strings, comments or other explicitly defined regions during recovery,
so their separators and closing delimiters do not terminate the item. Nesting is
not inferred. `maxSkippedBytes` applies separately to each malformed item. The
separator, close and optional ignore parser must be non-nullable.

## Custom recovery

`recoverWith()` takes a pure `callable(RecoveryContext): ?Span`. It runs only after
the operand fails. Return a span beginning at the operand's start to accept a
recovered region, or null to retain the original failure. Callable objects with
`__invoke(RecoveryContext): ?Span` can encapsulate reusable strategies.

```php
use Vanderlee\Comprehend\Extensions\Recovery\RecoveryContext;
use Vanderlee\Comprehend\Source\Span;

$declaration = $syntax->recoverWith($validDeclaration,
    static fn(RecoveryContext $context): ?Span => $context->scanUntil('next'),
    probes: ['next' => $syntax->text('function')],
);
```

This simple example resumes before the next literal `function`; it does not check
keyword boundaries, nesting or strings. Register an appropriate grammar probe and
implement those policies when the language needs them.

Both `$syntax->recoverWith($parser, $strategy, probes: [], maxSkippedBytes: null)` and
`$parser->recoverWith($strategy, probes: [], maxSkippedBytes: null)` are supported.
Facade probe values may be expressions, Parser objects or literal strings, keyed
by nonempty names. The `RecoverWith` object constructor accepts Parser probe
values. They are explicit graph dependencies, snapshotted, reference-resolved and
checked for non-nullability alongside the operand.

| Context member | Contract |
| --- | --- |
| `source`, `start`, `failure` | Original source, byte start and failed operand diagnostic |
| `probe(name, offset)` | Isolated syntax probe; returns its span or null without retaining semantics or effects |
| `readUnit(offset)` | Budgeted source-unit read; null at EOF or the skip limit |
| `regionTo(end)` | Construct a recovery span; null for empty or over-limit regions |
| `scanUntil(name, consumeBoundary: false, allowEof: false, ignore: null)` | Scan using registered boundary and optional ignore probe names |
| `consumeWork(amount: 1)` | Charge custom work not already covered by probes or reads |

All context positions must be source-unit boundaries at or after `start`. A
returned span must use the same source and start, consume input, end on a unit
boundary, and obey the byte limit. Invalid returns throw contract exceptions;
they are not treated as syntax errors. Context operations expire as soon as the
strategy returns or throws. Probe failures do not leak diagnostics, and probes
that themselves recover are not valid synchronization points.

Strategies must be pure and stable, just like recognition guards. Do not capture
mutable parsers and run a separate parse inside the callback; register probes and
use the context so they share graph snapshots, scopes, diagnostics and budgets.
Arbitrary callback loops cannot be preempted: use the budgeted operations and
`consumeWork()` for additional computation. Exceptions propagate normally.

Run the example with `php docs/examples/recovery.php`.
