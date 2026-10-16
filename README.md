# Comprehend

An interpreted parser-combinator framework for PHP 8.1+, with named grammars,
reusable RFC rules, explicit sources, immutable concrete trees and named ASTs.
MIT licensed. Requires JSON and mbstring. DOM is optional, needed only for XML output.

## Quick start

```php
require 'vendor/autoload.php';

use Vanderlee\Comprehend\Comprehend as C;

// Build a parser and describe the value to extract.
$age = C::integer(0, 150)
    ->named('age')
    ->mapText(static fn(string $text): int => (int) $text);

// Parse complete input, then extract the typed value.
$result = $age->parse('42');
if ($result->ok) {
    echo $result->output();  // 42 (int)
    echo $result->rawText(); // '42' (original text)
}
```

`ok` is a short alias for `success`; `output()` returns `value()` (original text
unless mapped). Failed results yield null output. Value access stays lazy and
does not validate or commit effects. Use `rawText()` whenever you need the source text.

For spaced syntax, pass an optional outer spacing policy for one facade call:

```php
$declaration = C::text('age')->text(':')->then($age);
$result = $declaration->parse('age : 42', spacing: C::text(' ')->many());
if ($result->success) {
    echo $result->ast()->firstNamed('age')->rawText(); // '42'
}
```

Per-call spacing applies between items; it does not trim leading/trailing text.
Local `spacing()` and `lexeme()` directives take precedence. The grammar remains
unchanged. Run `php docs/examples/quick-start.php` for checked examples.

`matchPrefix(Source, int $byteOffset = 0)` allows remaining input.
`parseAll(Source, int $byteOffset = 0)` requires the entire remainder to match.
Both return `ParseResult`; every public offset and span uses bytes. Choose
`Utf8Source` for validated Unicode code-point recognition or `ByteSource` for
arbitrary octets. The facade's `parse()` requires complete input; `parseAll()`
and `matchPrefix()` also accept strings as validated UTF-8 and freeze the current
grammar automatically. Facades made entirely of owned built-in nodes reuse their
prepared snapshot after the first call. Deferred rules, definitions and custom
parsers still refresh; freeze those explicitly for stable repeated parsing.
FrozenGrammar accepts strings too. Facade composition treats string arguments as Text literals.
Object constructors still take explicit parsers; arrays are not grammar syntax.

## Source, syntax and meaning

One runtime serves object grammars, Rulesets and RFC imports. Each recognition
creates a ParseSession. The immutable CST retains selected syntax, trivia and
exact source spans. `name()` marks semantic structure for `ast()`, whose raw text
always comes from source. Typed `mapValue()` mappings and named result collections
are lazy and cached per recognition. `onCommit()` effects receive ParseResult and
run only after explicit `commit()` and successful semantic validation.

```php
use Vanderlee\Comprehend\Result\ParseResult;

$number = C::text('42')
    ->named('number')
    ->mapValue(fn (ParseResult $r): int => (int) $r->rawText())
    ->validateSemantic(fn (ParseResult $r): bool => $r->value() > 0);
$result = $number->parse('42');
$result->value();     // 42, cached
$result->ast();       // named source structure
$result->commit();    // validates, then commits selected effects once
```

Spacing is explicit: `spacing(?Parser)` configures intervening trivia;
`trivia()` boundary parsers recognize leading/trailing material; `lexeme()`
suppresses automatic spacing within a subtree. CST and AST serialize directly
to array, JSON and XML. There is no separate Token output model.

## Named grammar authoring

Use `Ruleset::define(string, Comprehend|Parser|Definition|callable)`, explicit
facade-returning `rule(string, ...$arguments)` lookup and `root(string|Comprehend|Parser)` selection.
`ref()` remains the low-level Parser reference. Deferred references permit forward and
recursive definitions; an undefined name raises a grammar error. Factories must
return Comprehend values or low-level Parser instances. Definition generators
accept both, and `build()` returns a facade for direct composition and parsing.
Use `Stub::setParser(Parser)` only for low-level recursive object grammars.

`freeze()` resolves and snapshots a graph, rejecting known left recursion and
nullable repetition. The immutable `Comprehend` facade composes the same object
parsers without mutating reused containers. Ruleset constructor entries, definitions
and roots accept facade values directly and detach them internally at registration.
See [Grammar authoring](docs/grammar-authoring.md)
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
- [Separated lists](docs/separated-lists.md): bounds, boundary separators and lazy item values
- [Delimiters and value selection](docs/value-selection.md): `between()`, `thenIgnore()` and `ignoreThen()`
- [CST](docs/concrete-trees.md), [AST](docs/ast.md), [serialization](docs/serialization.md)
- [Semantic values](docs/semantic-values.md) and [lifecycle](docs/lifecycle.md)
- [RFC libraries](docs/libraries.md), [contributing](docs/contribute.md)
- [Phase 2A removal inventory](docs/canonical-runtime.md)

Install with Composer, then run:

```sh
composer check-platform-reqs
php vendor/bin/pest --do-not-cache-result
php tools/check-canonical.php
php docs/examples/execution.php
```

CI runs these checks on PHP 8.1–8.5, with an experimental PHP 8.6 job.
This is a breaking redesign: old matching entry points, offset/result adapters,
magic rule dispatch, coercion, automatic callbacks and Token projections are
removed. See the [breaking changes](docs/canonical-runtime.md#breaking-changes).
No backwards-compatibility window is provided.
