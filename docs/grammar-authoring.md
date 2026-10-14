# Named, frozen and fluent grammars

All authoring styles compose the same Parser objects and use the canonical
Source/ParseSession/ParseResult runtime. No grammar text compiler or second
recognition engine is introduced.

## Explicit names and roots

```php
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\Utf8Source;

$rules = new Ruleset();
$rules->root('word');
$rules->define('word', new Sequence(new Text('a'), $rules->ref('suffix')));
$rules->define('suffix', new Text('b'));
$grammar = $rules->freeze();
$grammar->parseAll(new Utf8Source('ab'))->success; // true
```

`define(string, Comprehend|Parser|Definition|callable)` registers a facade, typed parser or factory.
`ref(string, ...$arguments)` creates a deferred named reference; definitions can
be written in any order. `root(string|Comprehend|Parser)` chooses the start rule by name or
supplies its facade or parser directly. `rule()` is a direct convenience call to `ref()`.
References never guess a literal or class name. Missing names and invalid factory
returns are grammar errors. Named references preserve their Ruleset group in
AST/diagnostic output. Ruleset roots inherit the selected root's typed value
unless the Ruleset has its own mapping.

Ruleset constructor entries, `define()` and `root()` accept Comprehend values
directly. Registration exports a detached parser graph internally, preserving
facade isolation without explicit `parser()` calls. Deferred references still
consult their owning builder until freezing. Factories continue to return Parser.

## Resolved graph snapshots

`Parser::freeze()` returns a FrozenGrammar with only `matchPrefix()`, `parseAll()`
and read-only `analysisWarnings`. It resolves the root's reference graph, builds
factory implementations, snapshots Parser/Definition state and validates known
graph safety. Sharing and consuming recursive cycles are preserved. The result
does not expose its internal mutable parser objects.

Changing a builder, shared Sequence/Choice, Stub target or Definition after
freezing does not change that snapshot. Freeze again to capture new definitions
or factory output. Each freeze bypasses the builder's lazy implementation cache;
implementations in the frozen graph live for that snapshot's lifetime. Matching
it creates a new ParseSession and fresh semantic/effect caches each time.

Parameterized references use rule-scope identity, name and argument identity.
Equal scalar/array arguments reuse one implementation within a freeze; objects
and resources use identity, without serializing user objects. Recursive factories
must produce a finite graph of distinct parameterizations. Factories may run
during freezing and must not perform application effects. Factory output and RFC
imports are detached just like ordinary object grammar children:

```php
use Vanderlee\Comprehend\Library\Rfc3986;

$rfc = new Rfc3986();
$rules = new Ruleset();
$rules->define('scheme', $rfc->ref('scheme'))->root('scheme');
$scheme = $rules->freeze();
$rfc->define('scheme', new Text('changed'));
$scheme->parseAll(new Utf8Source('https'))->success; // still true
```

Snapshots copy declared Parser/Definition fields and arrays, including readonly
fields, without invoking constructors. Closures and non-parser service/value
objects remain caller-owned dependencies; freeze does not deep-freeze external
objects or variables captured by reference. Custom recognizers should keep their
grammar structure in declared Parser fields and keep external dependencies pure.
Dynamic child construction remains opaque to static analysis.

## Immutable fluent composition

The `Comprehend` facade returns a new value for every composition or metadata
operation. It uses Sequence, Choice, Repeat and the existing directives:

```php
use Vanderlee\Comprehend\Comprehend;

$base = Comprehend::text('a');
$longer = $base->then(Comprehend::text('b'));
$alternative = $base->or(Comprehend::text('c'));
$base->freeze()->parseAll(new Utf8Source('a'))->success; // true
$longer->freeze()->parseAll(new Utf8Source('ab'))->success; // true
$alternative->freeze()->parseAll(new Utf8Source('c'))->success; // true
```

The bundled RFC grammars import the facade as `C` with
`use Vanderlee\Comprehend\Comprehend as C;` to keep composition concise.

| Operation | Underlying behavior |
| --- | --- |
| `text()`, `char()`, `set()`, `range()`, `regex()`, `of(Parser)` | Wrap a detached object parser |
| `any()`, `nothing()`, `start()`, `end()`, `integer(minimum, maximum, base)` | Remaining terminals; integer defaults match the object parser (minimum 0, no maximum, base 10) |
| `literalWindow(literal)` | Regex matching with a proven finite literal read window |
| `ref(Ruleset, name, ...args)` | Deferred supported named reference |
| `sequence(...items)`, `then(...items)` | Sequence composition |
| `choice(...items)`, `or(...items)` | Choice composition inheriting the scoped ranking policy (first by default) |
| `first(...items)` / `firstOf(...items)` | Explicit local first-success choice |
| `longest(...items)` / `longestOf(...items)`, `shortest(...items)` / `shortestOf(...items)` | Explicit local choice ranking |
| `all(...items)`, `except(match, not)`, `not(item)` | Intersection (shortest accepted span), exclusion, negative lookahead |
| `repeat(min, max)` | Repeat with existing bounds/progress semantics |
| `some()` / `plus()`, `many()` / `star()` / `kleene()`, `maybe()` / `optional()` | One or more, zero or more, zero or one |
| `named()`, `mapValue()` | Metadata on a detached graph |
| `guard()`, `validateSemantic()`, `onCommit()` | Pure recognition predicate, selected semantic validation, explicit committed effects |
| `setResult()`, `concatResult()`, `pushResult()` | Cached named result collections, independent of source text |
| `diagnosticRule(name)` | A readable rule label for failures |
| `caseSensitive(bool)`, `prefer(preference)` | Scoped case and choice policy; preference uses the `Prefer::FIRST`, `LONGEST`, or `SHORTEST` constants |
| `spacing()`, `lexeme()`, `trivia()` | Existing scoped directives |
| `parser()` | Detached object graph for Parser-only factories/constructors |
| `freeze()` | Resolved, analyzed reusable snapshot |

Reference-bearing facade expressions deliberately resolve names at freeze,
after definitions are complete. Each fluent composition protects reused parser
containers; forward references still consult their builder until freezing.
Exporting via `parser()` does not expose the facade's internal containers.
Mutable object builders remain available: migrate shared `add()`/array edits to
facade `then()`/`or()`, or freeze a graph before changing its builders. Composition
retains nested container structure rather than silently flattening named groups.

`then()` and `or()` accept any number of facade values or Parser objects. With no
arguments they return the unchanged facade; supplied object parsers are detached.
Strings are still explicit `Comprehend::text(...)` calls. One variadic call groups
its supplied children together; successive calls retain the preceding expression
as a nested child, preserving names and scoped directives.

```php
$digits = Comprehend::range('0', '9')->some()->named('digits');
$bracketed = Comprehend::text('[')->then($digits, Comprehend::text(']'));
$grammar = $bracketed->or(Comprehend::text('none'), Comprehend::text('empty'));
$grammar->freeze()->parseAll(new Utf8Source('[123]'))->success; // true
```

Repetition shortcuts retain the same nullable-child rejection as `repeat()`,
including optional repetition. Lifecycle operations retain their distinct timing:
`guard()` receives ConcreteNode and may run speculatively; `validateSemantic()`
and `onCommit()` receive ParseResult. Reading values does not commit effects.
See [Lifecycle](lifecycle.md) for validation and commit behavior. Nested case or
preference directives use the innermost applicable override.

### Replacing the Library functions

The free functions in `Vanderlee\Comprehend\Library` have been removed. Import
`Vanderlee\Comprehend\Comprehend` and compose facade values instead:

| Former call | Comprehend replacement |
| --- | --- |
| `text($text)`, `char($char)`, `set($set)`, `range($first, $last)`, `regex($pattern)` | Corresponding `Comprehend::text/char/set/range/regex(...)` factory |
| `s(...$items)` | `Comprehend::sequence(...$items)` |
| `c(...$items)` | `Comprehend::choice(...$items)` |
| `star($parser)` | `Comprehend::of($parser)->repeat()` |
| `plus($parser)` | `Comprehend::of($parser)->repeat(1)` |
| `opt($parser)` | `Comprehend::of($parser)->repeat(0, 1)` |
| `repeat($min, $max, $parser)` | `Comprehend::of($parser)->repeat($min, $max)` |

Call `repeat()` directly on an existing Comprehend value. `char()` and `set()`
accept an optional `include` flag; `range()` accepts an optional `in` flag.
These retain the underlying terminal's byte/Unicode and exclusion semantics.
Supply facade values directly to Ruleset definitions or roots. Export through
`parser()` for factories or object constructors that require Parser; use
`freeze()` to resolve and recognize a complete grammar:

```php
$rules = new Ruleset();
$rules->define('digits', Comprehend::range('0', '9')->repeat(1));
$grammar = Comprehend::sequence(Comprehend::text('['), Comprehend::ref($rules, 'digits'), Comprehend::text(']'));
$grammar->freeze()->parseAll(new Utf8Source('[123]'))->success; // true
```

## Static and runtime safety

Freeze rejects known left-recursive cycles, including indirect cycles and cycles
after known nullable prefixes. Errors include the offending rule/parser path.
Consuming recursion is allowed. Known zero-width children of Repeat are rejected
even for optional/bounded repetition, matching the existing runtime progress
contract; maximum zero is allowed because no child executes. Undefined Stubs
also fail at freeze.

Nullability is a conservative structural property. Zero-width terminals and
negative lookahead are nullable; guards and input-dependent conditions are not
executed by analysis. Intersections may consume their shortest child, so a
zero-width required branch makes repetition unsafe. Valid grammars depending on
such conditions may need restructuring rather than disabling progress checks.

Opaque custom parsers appear in `analysisWarnings`. Analysis does not infer their
nullable or leading behavior, nor prove cycles through unknown prefixes safe.
Runtime checks still reject a parser re-entering at the same byte position and
a repeated successful child consuming zero bytes. Session state restores on
exceptions. This is not left-recursion support, memoization, or a general depth
or work limit by itself. [ParseLimits](file-input.md) adds optional per-session
depth and work budgets; it does not add memoization or wall-clock deadlines.

## Checked authoring examples

Run `php docs/examples/authoring.php`. The [complete example](examples/authoring.php)
implements integer arithmetic with multiplication precedence, addition,
parentheses and whitespace in object, Ruleset and facade styles. It verifies
accepted/rejected inputs, typed values, exact named AST shape, byte spans and raw
text. It also compares an RFC-based `scheme://host` grammar in all three styles;
paths are deliberately outside that example's grammar. CI runs both this script
and the canonical execution examples.
