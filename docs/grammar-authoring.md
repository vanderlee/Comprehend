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

`define(string, Parser|Definition|callable)` registers a typed parser or factory.
`ref(string, ...$arguments)` creates a deferred named reference; definitions can
be written in any order. `root(string|Parser)` chooses the start rule by name or
supplies its parser directly. `rule()` is a direct convenience call to `ref()`.
References never guess a literal or class name. Missing names and invalid factory
returns are grammar errors. Named references preserve their Ruleset group in
AST/diagnostic output. Ruleset roots inherit the selected root's typed value
unless the Ruleset has its own mapping.

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

The `Grammar` facade returns a new value for every composition or metadata
operation. It uses Sequence, Choice, Repeat and the existing directives:

```php
use Vanderlee\Comprehend\Grammar;

$base = Grammar::text('a');
$longer = $base->then(Grammar::text('b'));
$alternative = $base->or(Grammar::text('c'));
$base->freeze()->parseAll(new Utf8Source('a'))->success; // true
$longer->freeze()->parseAll(new Utf8Source('ab'))->success; // true
$alternative->freeze()->parseAll(new Utf8Source('c'))->success; // true
```

| Operation | Underlying behavior |
| --- | --- |
| `text()`, `regex()`, `of(Parser)` | Wrap a detached object parser |
| `ref(Ruleset, name, ...args)` | Deferred supported named reference |
| `sequence(...items)`, `then(item)` | Sequence composition |
| `choice(...items)`, `or(item)` | First-choice composition |
| `repeat(min, max)` | Repeat with existing bounds/progress semantics |
| `named()`, `mapValue()` | Metadata on a detached graph |
| `spacing()`, `lexeme()`, `trivia()` | Existing scoped directives |
| `parser()` | Detached object graph for factories/constructors |
| `freeze()` | Resolved, analyzed reusable snapshot |

Reference-bearing facade expressions deliberately resolve names at freeze,
after definitions are complete. Each fluent composition protects reused parser
containers; forward references still consult their builder until freezing.
Exporting via `parser()` does not expose the facade's internal containers.
Mutable object builders remain available: migrate shared `add()`/array edits to
facade `then()`/`or()`, or freeze a graph before changing its builders. Composition
retains nested container structure rather than silently flattening named groups.

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
or work limit; bounded execution remains phase 4 work.

## Checked authoring examples

Run `php docs/examples/authoring.php`. The [complete example](examples/authoring.php)
implements integer arithmetic with multiplication precedence, addition,
parentheses and whitespace in object, Ruleset and facade styles. It verifies
accepted/rejected inputs, typed values, exact named AST shape, byte spans and raw
text. It also compares an RFC-based `scheme://host` grammar in all three styles;
paths are deliberately outside that example's grammar. CI runs both this script
and the canonical execution examples.
