# Comprehend 2.0.0 breaking-release preparation

The 2.0.0 candidate exposes one canonical source/recognition/result/lifecycle
contract. PHP 8.1+, JSON and mbstring are required; DOM is optional for XML output. Existing 1.x callers
must migrate; removed APIs have no compatibility adapters or deprecation window.
The changelog remains marked Unreleased until publication is ready.

## Caller changes

Bundled extension entry classes now share a directory with their manifests:
`Extensions\Numbers\Numbers`, `Extensions\Extraction\Extraction`, and the same
`Extensions\Name\Name` pattern for other entry classes. Update direct imports;
factory names and supporting parser namespaces are unchanged. Optimizer continues
to use `Extensions\Optimizer\GraphOptimizer`.

RFC, SemVer, XML, and YAML grammar classes moved from the former Library namespaces
into each owning extension's `Grammar` namespace. For example, use
`Vanderlee\Comprehend\Extensions\Encodings\Grammar\Rfc4648` and
`Vanderlee\Comprehend\Extensions\Xml\Grammar\Xml`. The shared bases live in
core as `Builder\AbstractSpecification` and `Syntax\SpecificationExtension`;
Unicode helpers live in `Extensions\Shared\Internal`, and
YAML helpers live in `Extensions\Yaml\Internal`. Update direct imports; no aliases
remain. Custom grammar parser CST kinds now use their owning extension namespaces.
Independent subject providers expose convenience and explicit versioned factories,
including historical RFCs. They preserve textual values and existing grammar
coverage. See [the complete factory catalog](libraries.md).

ABNF compilation moved from `Abnf` to `Extensions\Abnf`, including `Compiler`,
`Reader`, and `SyntaxException`. Update direct imports and exception catches;
there are no aliases. The static `Compiler::compile()` arguments and its returned
`FrozenGrammar` are unchanged. The bundled extension autoloads directly without
registration. See [ABNF authoring](abnf.md).

The optimizer moved from `Builder\GraphOptimizer` to
`Extensions\Optimizer\GraphOptimizer`, and `Runtime\LexicalTable` moved to
`Extensions\Optimizer\LexicalTable`. No compatibility aliases remain. The bundled
optimizer loads through its extension manifest; default freezing stays optimized.
`FreezeFlags::NONE` bypasses optimizer discovery. Custom preparation providers
implement `Builder\GraphOptimization`; multiple discovered optimizers are an error.
Replace the former `"optimizer": true` declaration with a `providers` map from
provider classes to interface lists. `ExtensionLoader::optimizer()` is removed;
use `requireOne(GraphOptimization::class)`. Generic `all(interface)` lookup supports
multiple providers, including several classes in a single manifest. Instances are
shared with syntax factory loading. See [Extension capabilities](syntax-extensions.md#capability-providers).

The scanner classes `ReadRun`, `ReadWhile`, `ReadUntil`, `ReadWhileSet`,
`ReadWhileRange`, and `ReadRest` also moved from `Parser\Terminal` into
`Extensions\Optimizer\Parser`, without aliases. Update direct imports and any
scanner CST kind comparisons. All eight read/skip factories load automatically
from the Optimizer manifest and retain their signatures, including when graph
optimization is disabled. Their names are now discoverable and allow local
Syntax overrides. See [Reading and skipping runs](read-scanning.md).

The Integer parser moved from `Parser\Terminal\Integer` to
`Extensions\Numbers\Parser\IntegerNumber`, with no compatibility alias. Update direct
imports. The `integer()`, `binary()`, `octal()`, `hexadecimal()` and `octet()`
factories retain their previous arguments and add a trailing optional sign policy. They load the bundled Numbers extension
automatically. Their names are discoverable rather than reserved core names;
explicit Syntax registrations can override them locally.

The intermediate `Extensions\Integers` name is now `Extensions\Numbers`, including
its parser namespace. Update direct imports and extension class references; no
aliases remain. The new `float()` factory recognizes decimal/exponent text.
See [Numbers](numbers.md) for sign policies and numeric bounds.

Application extraction helpers live in the automatically loaded
[Extraction expansion](extraction.md). Static helpers such as `C::collect(...)`
and fluent mappings need no registration. Low-level
extraction classes moved from `Parser\Structure` to `Extensions\Extraction\Parser`.
Core `separatedBy()` projects item values; `thenIgnore()`, `ignoreThen()` and
`between()` select operand values without installing an extension. Static analysis uses
PHPDoc without executable PHPStan extensions; remove the former PHPStan configuration includes.

| Previous surface | Supported replacement |
| --- | --- |
| String `match()`/invocation and implicit complete-input assumptions | Explicit Source with `matchPrefix()` or `parseAll()` |
| Mixed character/byte offsets and legacy `length` | Canonical byte `start`, `end`, `consumedByteLength`; explicit code-point conversion |
| Mutable string `parse(&$input, ...)` extension | Typed `recognize(Source, int, ParseSession): AbstractMatch` |
| Magic Ruleset lookup and string/class fallback | `define()`, `ref()`, `root()` and frozen graph snapshots |
| Parser-returning `rule()` | Fluent facade-returning `rule()`; use `ref()` for Parser-only callers or direct recognition, and `rule()->freeze()` for facade recognition |
| Implementation-returning `Definition::build()` | Immutable facade-returning `build()`; generators and factories accept/return facades, while `implementation()` is the internal low-level boundary |
| String/array arguments implicitly becoming parsers | Explicit Parser constructors; immutable Comprehend facade accepts literal strings in composition, but no array grammar coercion |
| Declared facade factories | Shared static/fluent names now use authoring-only magic dispatch with typed factories; instance terminal calls append syntax instead of constructing standalone terminals |
| Facade `thenText()` and other terminal helpers; static repetition `someOf/manyOf/maybeOf/repeatOf()` | Fluent `text/char/set/range/...()` and static `some/many/maybe/repeat()`; metadata and directive operations dispatch directly to typed catalog callbacks |
| Integer character arguments | `chr()` for octets or explicit Unicode strings |
| String choice policies and `ChoicePolicy::assertValid()` | `ChoicePolicy::FIRST`, `LONGEST` or `SHORTEST` enum cases; convert configuration strings with `ChoicePolicy::from()` and use `->value` to export strings |
| Token naming/output tree | `name()`, immutable CST, optional named AST and direct serialization |
| Automatic callbacks and multi-argument callback coordinates | `onCommit(callable(ParseResult))` and explicit `commit()` |
| Validation used to reject speculative alternatives | Pure CST `guard()`; selected-scope `validateSemantic()` for meaning |
| Parent-dependent processors and mutable projection lifetime | Cached typed `mapValue()` and supported named collections |

Integer candidates exceeding PHP's integer range now trim to representable,
in-range prefixes rather than saturating during conversion. `parseAll()` rejects
the remaining suffix. Numeric leading zeros and custom digit alphabets are opt-in;
default range, sign and prefix-selection behavior otherwise remains unchanged.

For UTF-8 `éa`, positions are bytes 0, 2 and 3. A failed result consumes zero
bytes, with a separate farthest-failure diagnostic. Successful prefixes may
retain speculative diagnostics; inspect `success`, not diagnostic presence.

Whitespace is explicit. Boundary trivia recognizes leading/trailing material;
`spacing()` applies between consumed items; `lexeme()` suppresses spacing within
its subtree. `parseAll()` never silently trims. Raw source is preserved
independently of naming or transformations. Unnamed AST roots retain full spans,
and CST/AST JSON/XML follow the [canonical serialization schema](serialization.md),
not the previous Token schema.

Use [grammar authoring](grammar-authoring.md) for frozen graphs, factories, RFC
imports and immutable facade composition. Mutable builders are still available;
freeze before retaining a graph that must outlive changes. Guard/effect/mapper
closures still own their external dependencies. The [extension contract](source-and-extension-contract.md)
defines supported custom parser hooks and budget cooperation.

Semantic values, validation reports and errors are cached per recognition.
`commit()` validates the selected document before running effects once. A thrown
effect may leave earlier external effects performed; subsequent commits rethrow
the retained error rather than replaying the sequence. File sources retain owned
disk snapshots; close only after finishing source-dependent reads/effects. See
[file input](file-input.md), [lifecycle](lifecycle.md) and [ABNF subset](abnf.md).

## Verification and publication

```sh
composer validate --strict
composer check-platform-reqs
php vendor/bin/pest --do-not-cache-result
php tools/check-canonical.php
php docs/examples/execution.php
php docs/examples/authoring.php
php docs/examples/file-input.php
php docs/examples/abnf.php
php docs/examples/release.php
php tools/release-notes.php 2.0.0
```

The release example checks caller changes with canonical Source, frozen named
grammars, typed values, custom recognition and idempotent commit. Removed APIs
are not simulated or reintroduced to run an obsolete before-example.

Required CI jobs run on PHP 8.1–8.5; PHP 8.6 is experimental/nonblocking. A local
PHP 8.2 pass does not verify that matrix. The release workflow reuses the same
test workflow at the tagged commit, following
[GitHub's reusable-workflow contract](https://docs.github.com/en/actions/how-tos/reuse-automations/reuse-workflows).
Package versions come from VCS tags; Composer has no hard-coded version field.

Before publishing, review the candidate, update the 2.0.0 changelog header from
Unreleased to the release date, commit the reviewed changes and push the bare
`2.0.0` tag. That explicitly triggers `.github/workflows/release.yml`. It reruns
the required checks, requires a dated changelog entry, and publishes using
[`gh release create --verify-tag`](https://cli.github.com/manual/gh_release_create).
Existing releases are retained on a workflow rerun. Only the publish job has
contents-write permission. Verify package indexing separately after publication.

This working-tree preparation does not publish a tag/release or attest to a
remote matrix run. Publication remains pending until the reviewed release commit
is tagged and its required CI checks pass. Future release tags need their own
notes and an updated workflow trigger; this workflow targets the 2.0.0 break.
