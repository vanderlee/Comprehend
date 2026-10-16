# Comprehend 2.0.0 breaking-release preparation

The 2.0.0 candidate exposes one canonical source/recognition/result/lifecycle
contract. PHP 8.1+, JSON and mbstring are required; DOM is optional for XML output. Existing 1.x callers
must migrate; removed APIs have no compatibility adapters or deprecation window.
The changelog remains marked Unreleased until publication is ready.

## Caller changes

Application extraction helpers now require the opt-in [Binding expansion](binding.md).
Replace static helpers such as `C::collect(...)` with
`Binding::syntax()->collect(...)`, and attach existing expressions or rule
references with `$syntax->of($parser)` before fluent extraction calls. Low-level
extraction classes moved from `Parser\Structure` to `Extensions\Binding`.
Core separated lists now return matched text; use Binding's subclass for lists
of item values. Binding's PHPStan configuration is separate from core support.

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
