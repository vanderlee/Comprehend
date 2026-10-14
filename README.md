# Comprehend

An interpreted parser-combinator framework for PHP 8.2+, with named grammars,
reusable RFC rules, explicit sources, immutable concrete trees and named ASTs.
MIT licensed. Requires DOM, JSON and mbstring.

## Quick start

```php
require 'vendor/autoload.php';

use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\Utf8Source;

$grammar = new Sequence(new Text('hello'), new Text(' world'));
$result = $grammar->parseAll(new Utf8Source('hello world'));
if ($result->success) {
    echo $result->rawText();
    echo $result->node->toJson();
}
```

`matchPrefix(Source, int $byteOffset = 0)` allows remaining input.
`parseAll(Source, int $byteOffset = 0)` requires the entire remainder to match.
Both return `ParseResult`; every public offset and span uses bytes. Choose
`Utf8Source` for validated Unicode code-point recognition or `ByteSource` for
arbitrary octets. Constructors take explicit parser objects; strings and nested
arrays are not implicitly converted to grammar structures.

## Source, syntax and meaning

One runtime serves object grammars, Rulesets and RFC imports. Each recognition
creates a ParseSession. The immutable CST retains selected syntax, trivia and
exact source spans. `name()` marks semantic structure for `ast()`, whose raw text
always comes from source. Typed `mapValue()` mappings and named result collections
are lazy and cached per recognition. `onCommit()` effects receive ParseResult and
run only after explicit `commit()` and successful semantic validation.

```php
use Vanderlee\Comprehend\Result\ParseResult;

$number = (new Text('42'))
    ->name('number')
    ->mapValue(fn (ParseResult $r): int => (int) $r->rawText())
    ->validateSemantic(fn (ParseResult $r): bool => $r->value() > 0);
$result = $number->parseAll(new Utf8Source('42'));
$result->value();     // 42, cached
$result->ast();       // named source structure
$result->commit();    // validates, then commits selected effects once
```

Spacing is explicit: `spacing(?Parser)` configures intervening trivia;
`trivia()` boundary parsers recognize leading/trailing material; `lexeme()`
suppresses automatic spacing within a subtree. CST and AST serialize directly
to array, JSON and XML. There is no separate Token output model.

## Named grammar authoring

Use `Ruleset::define(string, Parser|Definition|callable)`, explicit
`ref(string, ...$arguments)` lookup and `root(string|Parser)` selection. Deferred references permit forward and
recursive definitions; an undefined name raises a grammar error. Factories must
return Parser. Use `Stub::setParser(Parser)` for recursive object grammars.

`freeze()` resolves and snapshots a graph, rejecting known left recursion and
nullable repetition. The immutable `Grammar` facade composes the same object
parsers without mutating reused containers. See [Grammar authoring](docs/grammar-authoring.md)
and its checked arithmetic/RFC examples. Mutable builders remain available;
changes affect future snapshots, while frozen grammars remain independent.

## Execution limits

Seekable file sources retain stable disk snapshots with bounded read pages and
incremental UTF-8 validation. Optional `ParseLimits` bound cooperative depth and
work. See [File input](docs/file-input.md) and [measured performance](docs/performance.md)
for regex limits, source lifetime and tree-retention costs.

Choice selects the first success by default; explicit first/longest/shortest
policies are available. Repetition is greedy. A later failure does not retry an
earlier successful choice or shrink a repeat. Nullable repetitions throw rather
than loop. Left recursion is unsupported; no memoization or linear-time guarantee
is provided. Parsers are interpreted combinators, not an LR parse-table engine.
The documented [ABNF subset](docs/abnf.md) compiles to the same graph; arbitrary
BNF/EBNF and generalized ambiguity handling are unsupported. Sources may be in memory
or seekable disk snapshots. General regexes allocate full subjects; diagnostic
line indexing scans the source in chunks and retains line offsets.

## Documentation and verification

The [2.0.0 breaking-release guide](docs/breaking-release.md) covers caller changes,
supported PHP versions and the gated publication workflow.

- [Tutorial](docs/tutorial.md), [builder](docs/builder.md), [reference](docs/reference.md)
- [Sources](docs/sources.md) and [custom parser contract](docs/source-and-extension-contract.md)
- [Consumption](docs/consumption.md), [diagnostics](docs/diagnostics.md), [trivia](docs/trivia.md)
- [CST](docs/concrete-trees.md), [AST](docs/ast.md), [serialization](docs/serialization.md)
- [Semantic values](docs/semantic-values.md) and [lifecycle](docs/lifecycle.md)
- [RFC libraries](docs/libraries.md), [contributing](docs/contribute.md)
- [Phase 2A removal inventory](docs/canonical-runtime.md) and [evolution plan](docs/comprehend--review.md)

Install with Composer, then run:

```sh
composer check-platform-reqs
php vendor/phpunit/phpunit/phpunit --do-not-cache-result
php tools/check-canonical.php
php docs/examples/execution.php
```

CI runs these checks on PHP 8.2–8.5, with an experimental PHP 8.6 job.
This is a breaking redesign: old matching entry points, offset/result adapters,
magic rule dispatch, coercion, automatic callbacks and Token projections are
removed. See the [breaking changes](docs/canonical-runtime.md#breaking-changes).
No backwards-compatibility window is provided.
