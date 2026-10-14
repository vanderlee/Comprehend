# Canonical runtime and Phase 2A removal inventory

Phase 2A implements the mandatory cleanup before Phase 3. The runtime adopts
's separation of source access, recognition, concrete trees and named ASTs
as independently implemented ideas. It uses stricter byte-coordinate, immutable
snapshot, cached semantic and explicit-commit contracts. 's mutable fluent
aliasing, unresolved literal fallback and transformed/lossy AST content are not
part of this design.

```text
typed objects / Ruleset factories / RFC imports
                    |
           Source + ParseSession
                    |
       ParseResult + diagnostics + CST
                    |
         AST / typed value / collections
                    |
           validation + explicit commit
```

## Closed removal inventory

| Removed surface | Supported replacement | Verification |
|---|---|---|
| String/context runtime and protected `parse(&$input, ...)` | Typed `recognize(Source, int, ParseSession)` and one checked delegation path | Terminal/combinator fixtures, Unicode and custom extension tests |
| `match()`, `matchSource()`, `parseSource()`, unresolved/ASCII matching bridges and Parser invocation | `matchPrefix()` and `parseAll()` returning ParseResult | Consumption tests and canonical API gate |
| `supportsSource()` allowlist/opt-in, ASCII fallback, compatibility mode flags | Every subclass implements the same source contract | Custom parser and Source-interface tests |
| CoordinatePolicy, Coordinates, CoordinateToken, LegacyParserAdapter | Canonical byte spans; explicit source code-point conversions | Source and diagnostics tests |
| LegacySpacing and independent-context spacing dispatch | Scoped intervening trivia in the owning session, explicit boundary trivia and lexeme suppression | Trivia, effect order, case-policy and scope-restoration tests |
| Automatic callbacks and four-argument string callback signatures | `onCommit(callable(ParseResult))`, semantic validation and idempotent commit | Lifecycle tests, discarded attempts and retained errors |
| TokenProjection, Core Token and parser/match token callbacks | Direct immutable CST/AST array, JSON and XML schemas | TreeSerializationTest, AST and concrete-node tests |
| Token transparency, token child indexes and compatibility-only serialization layouts | Complete CST children, named AST projection and semantic-leaf markers | Punctuation, unnamed input, trivia and empty-span tests |
| ArgumentsTrait and string/int/array/class-name rule coercion | Explicit typed Parser arguments and factory returns | Constructor rejection and factory-contract tests |
| Magic instance/static Ruleset dispatch and shared rule registry | `define()`, `rule()`, `defined()`, `undefine()` on each instance | Rule isolation, forward references and missing-name tests |
| Magic Stub/Implementation properties and Definition invocation | `Stub::setParser()/getParser()`, `Definition::build()` | Recursive examples and canonical API gate |
| Definition's speculative validation and parent-dependent processors | ParseResult semantic validators and cached processors over local collections | Selected validation, caching and abandoned-definition tests |
| Compatibility-only fixtures and obsolete documentation | Canonical behavior fixtures, updated contracts and runnable examples | Full suite, PHP syntax/API gate and example execution |

Internal `Match\AbstractMatch`, Success and Failure are selected execution records,
not additional public result shapes. They retain lazy operations and observed
recognition progress; consumers use ParseResult and immutable source trees.

## Breaking changes

Pass `new Utf8Source($text)` or `new ByteSource($bytes)` explicitly. Replace old
matching calls with `matchPrefix()` or `parseAll()`. Success is `->success`;
consumption is `->consumedByteLength`, zero on failure; observed failure position
is `->farthestFailure` with structured diagnostics. Use `results()`/`result()` for
collections, `value()` for typed output and `node`/`ast()` for syntax. No coordinate
adapter or old output wrapper remains.

Construct `new Sequence(new Text('a'), new Text('b'))`, not string/array shorthand.
Call `rule('name', ...$arguments)` explicitly; supply Parser, Definition or a
factory to `define()`. Strings are not rule aliases or class-name factories.
Use `chr()` explicitly for octets passed to Char/Range. Replace `token()` naming
with `name()`; serializers operate on CST/AST, with the schema in
[serialization](serialization.md). Replace `callback()` with `onCommit()` and
take ParseResult as its only argument. Definition validators likewise receive
ParseResult; processors receive local collection arrays, without parent output.

`spacing(null)` disables intervening trivia; omission inherits the scoped policy.
Boolean spacing toggles are removed. Intervening policy applies between grammar
items, including zero-width items. Automatic trivia shares session case/choice
policy and disables recursive automatic spacing within the trivia grammar.
Leading/trailing material must be explicit. Nothing silently consumes boundary
trivia. Accepted trivia stays in the CST and participates in validation/commit,
but automatic trivia does not add named collections or AST nodes.

## Verification and remaining work

The local Phase 2A suite and examples are verified on PHP 8.2. The existing CI
matrix runs the suite, syntax/API removal gate, Composer checks and examples on
PHP 8.2–8.5; those other runtimes are not locally installed. No remote CI run is
claimed by this document.

Phase 3 still owns graph-wide reference resolution/freezing, the new named graph
API, immutable fluent composition, graph analysis and facade equivalence. The
explicit `rule()` operation here replaces magic lookup without introducing a
second authoring runtime. File input, budgets and performance work remain Phase 4.
