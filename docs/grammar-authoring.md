# Named, frozen and fluent grammars

All authoring styles compose the same Parser objects and use the canonical
Source/ParseSession/ParseResult runtime. No grammar text compiler or second
recognition engine is introduced.

Facade `sequence()` arguments retain insertion order, including named variadic
arguments. An unannotated sequence prefix is flattened with either calling style;
named nodes and explicit spacing policies preserve their composition boundaries.

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
supplies its facade or parser directly. `rule()` returns a Comprehend facade around
the deferred reference, while `ref()` returns the RuleReference parser itself.

```php
use Vanderlee\Comprehend\Comprehend;

$digits = $rules->rule('digit')->some();
$rules->define('digit', Comprehend::range('0', '9'));
$digits->freeze()->parseAll(new Utf8Source('123'))->success; // true
```

Use `ref()` in low-level Parser-only object constructors. Facade references
are recognized through `parse()`, `parseAll()` or `matchPrefix()` (which freeze
automatically), or through an explicitly retained frozen grammar;
they retain forward/recursive lookup, factory arguments and named rule groups.
References never guess a literal or class name. Missing names and invalid factory
returns are grammar errors. Named references preserve their Ruleset group in
AST/diagnostic output. Ruleset roots inherit the selected root's typed value
unless the Ruleset has its own mapping.

Ruleset constructor entries, `define()` and `root()` accept Comprehend values
directly. Registration exports a detached parser graph internally, preserving
facade isolation without explicit `parser()` calls. Deferred references still
consult their owning builder until freezing. Factories can accept and return
Comprehend values directly; Parser returns remain supported for extensions.
Factory arguments are passed unchanged. Definition accepts facade generators,
and `build(...arguments)` returns an independent immutable facade that can be
composed directly. `Comprehend::of(Definition)` builds its unparameterized facade.
Factories remain deferred until recognition or freezing.

## Resolved graph snapshots

`freeze()` returns a FrozenGrammar with `parse()`, `matchPrefix()`, `parseAll()`
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

Snapshots clone Parser/Definition objects and register each clone before copying
its owned grammar dependencies. Constructors do not run; custom `__clone()` hooks
do run and must not perform application effects. Metadata arrays use PHP's
copy-on-write behavior. Closures and non-parser service/value objects remain
caller-owned dependencies; freeze does not deep-freeze external objects or
variables captured by reference.

Custom composite recognizers must expose dependencies through `children()` and
copy them in `copyDependencies(GraphSnapshot)`, calling the parent hook first.
Owned dependency fields must permit replacement on the clone; scalar readonly
fields can stay readonly. See the [snapshot copying contract](source-and-extension-contract.md#snapshot-copying).
Dynamic child construction remains opaque to static analysis. Rulesets, deferred
references and factory implementations retain specialized snapshot methods for
resolution, pruning and cache refresh.

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
| `text()`, `char()`, `set()`, `range()`, `regex()`, `of(Comprehend\|Parser\|Definition)` | Immutable public syntax; wrap custom parsers or build definitions |
| `any()`, `nothing()`, `start()`, `end()` | Remaining terminals |
| `integer(minimum, maximum, base, allowLeadingZeros, digits)` | Bounded numeric text; defaults are minimum 0, no maximum, base 10, no leading zeros and the standard digit alphabet |
| `binary()`, `octal()`, `hexadecimal()`, `octet()` | Base-2/8/16 factories and decimal range 0–255; optional leading zeros |
| `literalWindow(literal)` | Regex matching with a proven finite literal read window |
| `ref(Ruleset, name, ...args)` | Deferred supported named reference |
| `sequence(...items)`, `then(...items)` | Sequence composition |
| `prepend(...items)` | Sequence the supplied items before the current expression, preserving their order; no arguments returns the same facade |
| `->text()`, `->char()`, `->set()`, `->range()`, `->regex()`, `->literalWindow()` | Append a terminal with the same arguments/defaults as its static factory |
| `->any()`, `->nothing()`, `->start()`, `->end()` | Append unit, empty or boundary terminals |
| `->integer()`, `->binary()`, `->octal()`, `->hexadecimal()`, `->octet()` | Append numeric terminals with the same options as their factories |
| `choice(...items)`, `or(...items)` | Choice composition inheriting the scoped ranking policy (first by default) |
| `first(...items)` | Explicit local first-success choice |
| `longest(...items)`, `shortest(...items)` | Explicit local choice ranking |
| `all(...items)`, `except(match, not)`, `not(item)` | Intersection (shortest accepted span), exclusion, negative lookahead |
| `repeat(min, max)` | Repeat with existing bounds/progress semantics |
| `some()` / `plus()`, `many()` / `star()` / `kleene()`, `maybe()` / `optional()` | One or more, zero or more, zero or one |
| `some(item)`, `many(item)`, `maybe(item)`, `repeat(item, min, max)` | Static repetition factories accepting a facade, Parser or literal string |
| `named(string\|int\|callable)`, `tag(...tags)`, `mapValue()` | Fixed or pure dynamic names, tags and semantic mappings on a detached graph |
| Extraction's `mapText(callable)` | Text mapping through the automatically loaded [Extraction expansion](extraction.md) |
| `guard()`, `validateSemantic()`, `onCommit()`, `assignTo()` | Pure recognition predicate, selected semantic validation, explicit committed effects and assignments |
| `setResult()`, `concatResult()`, `pushResult()` | Cached named result collections, independent of source text |
| `label(text)` | Replace entry-position expectations, preserving deeper failures; see [labels](diagnostics.md#expectation-labels) |
| `diagnosticRule(name)` | A readable rule-path name, separate from expectation labels and tree names |
| `caseSensitive(bool)`, `prefer(preference)` | Scoped case and choice policy; preference uses `Runtime\ChoicePolicy::FIRST`, `LONGEST`, or `SHORTEST` |
| `spacing()`, `lexeme()`, `trivia()` | Existing scoped directives |
| `parser()` | Detached object graph for low-level integration |
| `freeze()` | Resolved, analyzed reusable snapshot |
| `parse(input)`, `parseAll(input)`, `matchPrefix(input)` | Freeze automatically and recognize a Source or validated UTF-8 string; parse/parseAll require complete input |

Reference-bearing facade expressions deliberately resolve names at freeze,
after definitions are complete. Each fluent composition protects reused parser
containers; forward references still consult their builder until freezing.
Exporting via `parser()` does not expose the facade's internal containers.
Mutable object builders remain available: migrate shared `add()`/array edits to
facade `then()`/`or()`, or freeze a graph before changing its builders. Composition
flattens anonymous sequence prefixes while retaining named and decorated groups.

`then()` and `or()` accept any number of facade values, Parser objects or strings. With no
arguments they return the unchanged facade; supplied object parsers are detached.
String arguments become Text literals in sequence, choice, chaining, intersection,
exclusion and negative lookahead. Empty strings retain Text's rejection; use
`nothing()` for a zero-width match. Object constructors remain Parser-only, and
rule lookup still requires explicit names. One variadic call groups
its supplied children together. Successive sequence calls flatten an anonymous
sequence prefix, reducing nesting in chains such as `text('a')->text('b')->text('c')`.
Names, tags, semantic callbacks, result operations, assignments, diagnostic labels,
explicit sequence spacing and subclass boundaries prevent flattening. Directive
wrappers remain intact. Non-prefix nested sequences are retained because nullable
items can otherwise change how often spacing is consumed. Empty prefixes and
recursive boundaries are retained as well. Anonymous CST grouping and recognition
depth/work counts can therefore differ; source spans and named semantic boundaries remain.

Anonymous choices that inherit their policy also flatten during `choice()`,
`or()` and explicit ranked-factory composition. Chained `text('a')->or('b')->or('c')`
therefore has one choice container. Explicit first/longest/shortest policies,
metadata, subclasses and directive wrappers retain their boundaries. Alternative
order and ranking ties remain unchanged. As with sequences, anonymous CST nesting
and recognition work/depth counts may change; reused expressions remain immutable.

```php
$digits = Comprehend::range('0', '9')->some()->named('digits');
$bracketed = Comprehend::sequence('[', $digits, ']');
$grammar = $bracketed->or('none', 'empty');
$grammar->parse('[123]')->success; // true
```

`prepend(...items)` is the immutable counterpart of `then(...items)`: it constructs
`sequence(...items, currentExpression)` and detaches supplied Parser objects. Names,
policies and callbacks on the current expression stay attached to that subtree.
The returned expression retains its custom syntax scope.

```php
use Vanderlee\Comprehend\Extensions\Extraction;

$word = Extraction::syntax()->text('hello')->mapText('strtoupper');
$word->parse('hello')->output(); // 'HELLO'; rawText() remains 'hello'
$wrapped = $word->prepend('[')->text(']');
$wrapped->parse('[hello]')->rawText(); // '[hello]'; $word still matches 'hello'
```

`mapText()` accepts a callable taking the original matched string and returning any
value. It shares `mapValue()`'s lazy cache and effect boundaries: failed parses do
not call it, and reading output does not commit effects. Both configure the same
value mapper, so a later mapping replaces the earlier one rather than forming a
transformation pipeline. A mapping on a child remains local to that child; map the
completed composition to transform its whole value.

Repetition shortcuts retain the same nullable-child rejection as `repeat()`,
including optional repetition. Lifecycle operations retain their distinct timing:
`guard()` receives ConcreteNode and may run speculatively; `validateSemantic()`
and `onCommit()` receive ParseResult. Reading values does not commit effects.
See [Lifecycle](lifecycle.md) for validation and commit behavior. Nested case or
preference directives use the innermost applicable override.

Choice ranking values live in `Vanderlee\Comprehend\Runtime\ChoicePolicy`:
`FIRST`, `LONGEST` and `SHORTEST` are backed enum cases. Pass a case to `prefer()`,
`Choice::setPreference()`, `Prefer`, `ParseSession` and the ABNF compiler's `choice`
option. Strings are rejected with TypeError. At a configuration boundary, use
`ChoicePolicy::from($value)` to validate a string, or `tryFrom()` to handle invalid
input explicitly. Read `->value` for the strings `first`, `longest` and `shortest`.
`setPreference(null)` restores inherited policy. The `Prefer` directive applies
the policy to a subtree.

### Terminal chaining and static repetition

```php
$token = Comprehend::text('a')->set('01')->text('!');
$token->parse('a1!')->success; // true
$digits = Comprehend::some(Comprehend::range('0', '9'));
$suffix = Comprehend::maybe('!');
```

Terminal chains sequence a new terminal after the existing expression, preserving
named groups, scoped policies and immutable reuse. To name, tag or map only the
appended terminal, compose it separately and pass it to `then()`.
Shared names now use `__call()`/`__callStatic()` only at the facade authoring
boundary. Static calls construct syntax; fluent factory calls append it.
Fluent `or()` extends alternatives instead of sequencing, while static `or()` is
a choice factory. Static `repeat(item, min, max)` and fluent `repeat(min, max)`
retain distinct signatures. Static some/many/maybe require an item; their fluent
forms take no arguments and repeat the current expression. Typed catalog callbacks
validate arguments and preserve named arguments. Terminal chains use these shared
short names; redundant `thenText()`-style helpers and static repetition `*Of()`
helpers have been removed.
All repetition bounds, nullable-child checks and deferred references are unchanged.

### Scoped expandable syntax

Optional factory bundles can implement `Syntax\SyntaxExtension` and be installed
with `$syntax->extend($extension)`. See [Syntax extensions](syntax-extensions.md)
for generator-based authoring and the optional ASCII identifier/keyword vocabulary.

```php
$syntax = Comprehend::syntax()->defineRule('word',
    static fn(): Comprehend => Comprehend::range('a', 'z')->some()->lexeme()->named('word'));
$grammar = $syntax->text('let ')->word()->text(';');
$grammar->parse('let name;')->success; // true
```

`defineRule(name, callable)` returns a new immutable Syntax vocabulary. Factories
may return Comprehend, custom Parser or Definition; any other result throws.
Factories run during authoring, and should only construct syntax. Named/default
parameters and variadic arguments follow the factory's typed signature. There
is no implicit conversion of invalid scalar arguments or fallback to rule names.
Unknown syntax, surplus nonvariadic arguments and reserved names raise explicit errors.

For transformations of the current expression, `defineFluent(name, callable)`
registers a callback receiving the current Comprehend as its first parameter,
followed by user arguments. A fluent-only name cannot start syntax. A name
may have separate static and fluent definitions in the same vocabulary.
Without a custom fluent, calling a registered static factory fluently appends it.

```php
$syntax = $syntax->defineFluent('surround',
    static fn(Comprehend $item, string $left, string $right): Comprehend =>
        Comprehend::sequence($left, $item, $right));
$grammar = $syntax->word()->surround('(', ')');
```

Custom syntax works for static and fluent factories within that vocabulary. Facade
metadata/composition operations retain their scope; define a replacement in a
new vocabulary without changing existing expressions. No global registration
affects `Comprehend::word()` or other vocabularies. Build through `$syntax` to
select the scope explicitly, or use `$syntax->call(name, ...arguments)` when the
method name is data. `names()` lists built-in and registered factory names.
Freezing performs no syntax dispatch. Its optimizer replaces eligible recognition
operations with specialized parsers, retaining the Repeat boundary and each
original per-unit CST node:

| Repeated child | Scanner | Conditions |
|---|---|---|
| Inclusive `Set` | `ReadWhileSet` | Plain built-in child |
| Inclusive `Range` | `ReadWhileRange` | ASCII or open bounds, ordered before and after case folding |
| Inclusive `Char` | `ReadWhileSet` | ASCII character |
| Excluded `Char` | `ReadUntil` | ASCII character; stops before the excluded unit |
| `Text` | `ReadWhileSet` | One ASCII character |
| `Any` | `ReadWhileRange` over all units | Ignores case policy, as the original child does |

All rewrites preserve repetition bounds. Literal and range scanners inherit the
child's local or enclosing case policy. Names, tags, groups, guards, lazy values,
validators and commit effects on the repeat remain intact. Decorated children,
excluded sets/ranges, non-ASCII literal/range bounds, multi-unit text and custom
subclasses retain their original recognition. Maximum zero never scans. Active
intervening spacing or work/depth limits use ordinary repetition so trivia,
diagnostics and budget failures retain their contracts. Authoring copies do not
run the optimizer; mutable builders remain unchanged.

Control optimization when preparing a grammar with `Builder\FreezeFlags`:

The bundled `Extensions\Optimizer\GraphOptimizer` is discovered through
`extensions/Optimizer.extension.json`. Core combinators retain ordinary recognition;
the extension supplies internal parser implementations for optimized execution.
Their concrete syntax trees retain the original parser kinds.

```php
use Vanderlee\Comprehend\Builder\FreezeFlags;
use Vanderlee\Comprehend\Builder\FrozenGrammar;

$optimized = $grammar->freeze(); // FreezeFlags::DEFAULT enables OPTIMIZE.
$plain = $grammar->freeze(flags: FreezeFlags::NONE);
$explicit = FrozenGrammar::from($grammar, FreezeFlags::OPTIMIZE);
$result = $plain->parse('input');
```

Both Parser and facade `freeze(int $flags = FreezeFlags::DEFAULT)` accept flags.
`NONE` disables only optimizer rewrites: graph isolation, reference resolution and
structural safety checks always run. Unknown flag bits raise
`InvalidArgumentException` before preparation. Flags support bitwise `|` composition;
`OPTIMIZE` is currently the only option. Reusable facade snapshots are cached
separately for each flag set. Normal facade parsing continues to use `DEFAULT`;
parse through an explicitly frozen grammar to disable optimization.

`NONE` does not resolve an optimizer provider. With `OPTIMIZE`, preparation requires
exactly one discovered optimizer; missing or competing providers raise an error.
Optimizer providers implement `Builder\GraphOptimization`: `replace()` creates
snapshot parsers, and `prepare()` initializes the isolated, validated graph.
Manifests advertise this interface in the class-to-interfaces `providers` map;
preparation resolves it through `ExtensionLoader::requireOne(GraphOptimization::class)`.
See [Extension capabilities](syntax-extensions.md#capability-providers) for generic discovery.

After references are resolved and graph safety checks pass, optimization also
prepares bounded lexical tables for Repeat operands, Choice alternatives and Not
probes. These cover named character rules, character-class choices, fixed escapes
such as URI `%HH`, finite group sequences and literal delimiter checks. First
observations use the original recognition runtime; later observations reproduce
fresh CST nodes and merge the same diagnostics under the current caller's rule
path. Original branch ordering and first/longest/shortest policies remain intact.

Tables retain at most 128 entries per operand, with at most 32 bytes of proven
ASCII lookahead. They retain relative syntax recipes, not Sources, ParseResults
or semantic state. Keys distinguish source mode, case policy and choice policy.
Non-ASCII lookahead, executable metadata (including dynamic names), custom
classes, recursive/unbounded patterns, local spacing inside a candidate and active spacing/budgets
use ordinary recognition. A narrow regex subset is eligible: a single character
class, optionally with a finite quantifier. There is no general regex conversion
or parse-position memoization. `FreezeFlags::NONE` disables these tables too.

See [Library optimizer measurements](performance.md#library-lexical-tables-2026-10-17)
for performance and retained-memory tradeoffs.

Built-in static calls such as `Comprehend::text()` resolve directly from a fixed
catalog. That catalog coordinates typed static/fluent callbacks, aliases,
name discovery and reserved names. Scoped Syntax vocabularies resolve callbacks
directly through their immutable catalog, without registries or resolution caches.
Factories run for every authoring call and parser expressions are never cached.
Extending a vocabulary creates a new catalog, leaving existing expressions unchanged.
Parser construction, repetition, composition, directives and fluent metadata are
implemented by catalog callbacks, dispatched directly through magic calls;
parsing, freezing, graph export and reference-based `assignTo()` remain declared on the facade.

Built-in `@method` annotations provide completion and return types. Repetition
annotations describe overloaded dispatch broadly; runtime signatures remain
strict. Custom names require consumer annotations or the explicit `call()` API
for tooling. Native reflection sees dispatch hooks and declared lifecycle operations,
not declared public methods for every syntax name. Reference parameters in custom
factories are unsupported by magic forwarding; use explicit facade lifecycle APIs
such as `assignTo()` for referenced assignments.

### Direct parsing and numeric factories

`parse()` is a complete-input alias for `parseAll()`. A facade whose entire graph
declares the `ReusableSnapshot` capability prepares its snapshot once per flag set and reuses it for later
calls, including explicit `freeze()` calls. This cache belongs to that immutable
expression; composition and metadata changes create a new expression. Recognition
results, semantic caches and effects remain separate for every parse.

Deferred rule references, Definition implementations and custom parsers without that capability
still freeze freshly on each call, so builder edits and factory state changes
remain visible. Custom subclasses do not inherit built-in cache eligibility merely
by inheriting the interface; they must explicitly declare their own guarantee.
See [preparation reuse](source-and-extension-contract.md#optional-preparation-reuse).
Retain `freeze()` once when those graphs should use stable definitions. Per-call
spacing composition creates a separate expression rather than a global cache.
Strings always mean validated UTF-8, never automatic encoding detection;
pass ByteSource for arbitrary bytes. Offsets and limits keep their canonical contracts.

Facade `parse()`, `parseAll()` and `matchPrefix()` also accept a fourth optional
`spacing: Comprehend|Parser|null` argument. A non-null value supplies an outer
spacing policy for that call, without modifying the grammar. Local `spacing()`
and `lexeme()` directives take precedence. Null or omission leaves the grammar's
spacing unchanged. This consumes intervening trivia only, not leading/trailing
material. For repeated parsing, configure `spacing()` before `freeze()`; frozen
entry points retain their existing signatures.

```php
// The bundled Numbers extension loads automatically; no registration is needed.
$hex = Comprehend::hexadecimal(0, 255, allowLeadingZeros: true)->caseSensitive(false);
$hex->parse('00FF')->success; // true
$hex->parse('100')->success;  // false: outside the range
$hex->matchPrefix('100')->rawText(); // '10': longest in-range prefix
Comprehend::integer(2, 2, base: 2, digits: 'αβ')->parse('βα')->success; // true
```

Numeric factories preserve raw text as their default value; use `mapValue()` for
typed application values. The digit alphabet is valid UTF-8 with unique code-point
symbols in numeric order, beginning with zero. Minus is reserved for the sign.
The base selects the initial symbols and must be at least two and no larger than
the alphabet. Case-insensitive matching rejects alphabets with colliding folded
symbols. Leading zeros, including signed zero, are opt-in. Out-of-range and
overflowing candidates are trimmed to whole-digit prefixes; full-input parsing
rejects an unconsumed suffix. PHP_INT_MIN and PHP_INT_MAX are matched exactly.
Base factories recognize digits without prefixes such as `0x` or `0b`.

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
`parser()` only for low-level object constructors or integration that require Parser; use
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
Custom parsers can opt into these checks through the
[optional structural analysis contract](source-and-extension-contract.md#optional-structural-analysis),
without changing the analyzer. Built-ins implement that same contract; subclasses
that change recognition must also maintain their inherited analysis declarations.
Runtime checks still reject a parser re-entering at the same byte position and
a repeated successful child consuming zero bytes. Session state restores on
exceptions. This is not left-recursion support, memoization, or a general depth
or work limit by itself. [ParseLimits](file-input.md) adds optional per-session
depth and work budgets; it does not add memoization or wall-clock deadlines.

## Checked authoring examples

Run `php docs/examples/authoring.php`. The [complete example](https://github.com/vanderlee/Comprehend/blob/master/docs/examples/authoring.php)
implements integer arithmetic with multiplication precedence, addition,
parentheses and whitespace in object, Ruleset and facade styles. It verifies
accepted/rejected inputs, typed values, exact named AST shape, byte spans and raw
text. It also compares an RFC-based `scheme://host` grammar in all three styles;
paths are deliberately outside that example's grammar. CI runs both this script
and the canonical execution examples.
