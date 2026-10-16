# Supported API reference

| Concern | API |
|---|---|
| Input | Source, ByteSource, Utf8Source, ByteFileSource, Utf8FileSource, EncodedFileSource, SourceMode, immutable Span |
| Runtime | ParseSession; checked `recognizeInSession(Source, int, ParseSession)` delegation |
| Entry points | `matchPrefix(Source, int = 0, ?ParseLimits = null)`, `parseAll(Source, int = 0, ?ParseLimits = null)` |
| Public entry points | Facade and frozen `parse/parseAll/matchPrefix(Source\|string, int = 0, ?ParseLimits = null)` |
| Per-call spacing | Facade entry points accept a fourth `spacing: Comprehend\|Parser\|null` argument; local grammar policies take precedence |
| Limits/profile | ParseLimits(maxDepth, maxWork), ParseLimitException, optional bounded ParseMetrics |
| Bounded regex | `Regex::literalWindow(string)` for quoted literals; general Regex keeps full-subject semantics |
| Terminals | Text(string), Char(string, bool = true), Any, Set(string, bool = true), Range(?string, ?string, bool = true), Regex(string), Integer, Start, End, Nothing |
| Structures | Sequence(Parser...), Choice(Parser...), All(Parser...), Except(Parser, Parser), Not(Parser), Repeat(Parser, int = 0, ?int = null) |
| Repetition | `Comprehend::of(Parser)->repeat(min, max)`; object factories `Repeat::plus/star/optional/exact` |
| Separated lists | `item->separatedBy(separator, min: 0, max: null, allowLeading: false, allowTrailing: false)`; static `separatedBy(item, separator, ...)`, object `SeparatedList(Parser, Parser, ...)`; [behavior and values](separated-lists.md) |
| Shared repetition names | Static `some/many/maybe(item)`, `repeat(item, min, max)`; fluent `some/many/maybe()`, `repeat(min, max)` |
| Terminal chaining | `->text/char/set/range/regex/literalWindow/any/nothing/start/end/integer/binary/octal/hexadecimal/octet(...)` |
| Choice policy | `first/longest/shortest(Parser...)`, scoped Prefer |
| Scoped policy | CaseSensitive(bool, Parser), Space(?Parser, Parser), `spacing(?Parser)`, `lexeme()`, `trivia()` |
| Grammar | Ruleset, Definition, Implementation, RuleReference, explicit Stub setter |
| Named operations | `define(string, Comprehend\|Parser\|Definition\|callable)`, `ref(string, ...args)`, `root(string\|Comprehend\|Parser)` |
| Frozen graphs | `freeze()` returning FrozenGrammar with syntax checks and analysisWarnings |
| Immutable facade | Comprehend text/char/set/range/regex/ref, sequence/choice, then/or/repeat, naming and scoped policy |
| Expandable syntax | `Comprehend::syntax()->define(name, factory)`; immutable scoped static/fluent factories, `call()`, `names()` |
| ABNF authoring | `Abnf\Compiler::compile(string, root, numericEncoding, choice)` returning FrozenGrammar |
| Naming | `name(string|int, ?string = null, bool $leaf = false)` |
| Meaning | `mapValue(callable)`, named set/concat/push collection operations |
| Text mapping | `mapText(callable(string): mixed)` maps original matched text lazily, replacing any previous value mapper |
| Prepending | Facade `prepend(Comprehend\|Parser\|string ...items)` returns an immutable sequence of the supplied items followed by the current expression |
| Lifecycle | `guard(callable)`, `validateSemantic(callable)`, `onCommit(callable)`, assignments |
| Results | ParseResult, Diagnostic, ValidationResult, immutable ConcreteNode and AstNode |
| Simple result access | Readonly `ok` aliases `success`; `output()` aliases lazy typed `value()`, returning null on syntax failure without committing effects |
| AST navigation | `child()`, `firstChild()`, `lastChild()`, `firstNamed()`, `allNamed()`, `firstInGroup()`, `allInGroup()`, `descendants()` |
| Tree metadata | `tag()`, fixed or callable `named()`, `hasTag()`, `firstTagged()`, `allTagged()`, AST preamble/postamble spans and text helpers |
| Serialization | CST/AST `toArray()`, `toJson()`, JsonSerializable; `toXml()` returns DOMDocument and requires optional ext-dom |

Use `Comprehend` for concise authoring. It returns immutable facade values;
Ruleset constructor entries, definitions and roots accept those values directly
and detach their parser graphs at registration. `parser()` exports a detached
Parser only for low-level integration. Factories can return facade values;
Definition accepts facades and `build()` returns a facade. Wrap a custom Parser
with `Comprehend::of()` to use the public authoring API.
The former free functions in `Vanderlee\Comprehend\Library` have been removed.

See [Grammar authoring](grammar-authoring.md) for frozen factory lifetimes,
immutable composition, safety limits and checked arithmetic/RFC examples.

See [ABNF](abnf.md) for the supported text subset and [breaking release](breaking-release.md)
for caller changes, supported runtimes and publication gates.
Integer recognizes bounded integers; its bounds must be integers/null. The default
alphabet supports bases 2–36. Custom UTF-8 alphabets and opt-in leading zeros are
supported by Integer and the facade's `integer()`; `binary()`, `octal()`,
`hexadecimal()` and `octet()` provide common factories. Overflow is rejected or
trimmed to an in-range prefix, never saturated. See [Grammar authoring](grammar-authoring.md).
Use explicit `chr()` for octet literals, not integer-to-character coercion.

See [custom extensions](source-and-extension-contract.md), [builders](builder.md),
[results](parse-results.md) and [breaking changes](canonical-runtime.md#breaking-changes).
