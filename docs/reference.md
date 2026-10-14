# Supported API reference

| Concern | API |
|---|---|
| Input | Source, ByteSource, Utf8Source, ByteFileSource, Utf8FileSource, SourceMode, immutable Span |
| Runtime | ParseSession; checked `recognizeInSession(Source, int, ParseSession)` delegation |
| Entry points | `matchPrefix(Source, int = 0, ?ParseLimits = null)`, `parseAll(Source, int = 0, ?ParseLimits = null)` |
| Limits/profile | ParseLimits(maxDepth, maxWork), ParseLimitException, optional bounded ParseMetrics |
| Bounded regex | `Regex::literalWindow(string)` for quoted literals; general Regex keeps full-subject semantics |
| Terminals | Text(string), Char(string, bool = true), Any, Set(string, bool = true), Range(?string, ?string, bool = true), Regex(string), Integer, Start, End, Nothing |
| Structures | Sequence(Parser...), Choice(Parser...), All(Parser...), Except(Parser, Parser), Not(Parser), Repeat(Parser, int = 0, ?int = null) |
| Repetition helpers | `plus(Parser)`, `star(Parser)`, `optional(Parser)`, `exact(Parser, int)` |
| Choice policy | `first/longest/shortest(Parser...)`, scoped Prefer |
| Scoped policy | CaseSensitive(bool, Parser), Space(?Parser, Parser), `spacing(?Parser)`, `lexeme()`, `trivia()` |
| Grammar | Ruleset, Definition, Implementation, RuleReference, explicit Stub setter |
| Named operations | `define(string, Parser\|Definition\|callable)`, `ref(string, ...args)`, `root(string\|Parser)` |
| Frozen graphs | `freeze()` returning FrozenGrammar with syntax checks and analysisWarnings |
| Immutable facade | Grammar text/regex/ref, sequence/choice, then/or/repeat, naming and scoped policy |
| Naming | `name(string|int, ?string = null, bool $leaf = false)` |
| Meaning | `mapValue(callable)`, named set/concat/push collection operations |
| Lifecycle | `guard(callable)`, `validateSemantic(callable)`, `onCommit(callable)`, assignments |
| Results | ParseResult, Diagnostic, ValidationResult, immutable ConcreteNode and AstNode |
| Serialization | CST/AST `toArray()`, `toJson()`, `toXml()`, JsonSerializable |

Typed helpers in `Vanderlee\Comprehend\Library` are autoloaded by Composer:
`text`, `char`, `set`, `range`, `regex`, `s`, `c`, `plus`, `star`, `opt`, `repeat`.
They construct the same objects and require explicit Parser arguments.

See [Grammar authoring](grammar-authoring.md) for frozen factory lifetimes,
immutable composition, safety limits and checked arithmetic/RFC examples.
Integer recognizes bounded base-2–36 integers; its bounds must be integers/null.
Use explicit `chr()` for octet literals, not integer-to-character coercion.

See [custom extensions](source-and-extension-contract.md), [builders](builder.md),
[results](parse-results.md) and [breaking changes](canonical-runtime.md#breaking-changes).
