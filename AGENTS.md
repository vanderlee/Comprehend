# Agent instructions

These instructions apply throughout this repository.

## Project and sources of guidance

Comprehend is a PHP 8.2+ parser-combinator library in the
`Vanderlee\Comprehend` namespace. It requires JSON and mbstring. DOM is optional
at runtime, and required for XML serialization and the test suite.

Use `README.md`, `docs/contribute.md`, relevant documentation in `docs/`,
and the existing implementation and tests to establish intended behavior.
Source lives in `src/`, Pest tests in `tests/`, runnable examples in
`docs/examples/`, and verification scripts in `tools/`.

## Implementation requirements

- Keep changes focused on the requested task. Preserve unrelated working-tree changes.
- Follow the existing typed PHP contracts and local conventions. Use the repository's
  PSR-12 and PHP-FIG PER style configurations; avoid unrelated formatting cleanup.
- Keep all authoring styles on the same typed parser graph and recognition runtime.
  Magic dispatch is limited to Comprehend/Syntax authoring.
- Preserve byte-based offsets and spans, pure recognition and guards, exception-safe
  scope restoration, immutable syntax trees, cached semantic projections, and explicit
  commit effects.
- Do not reintroduce removed matching entry points, compatibility adapters, magic
  Ruleset lookup, coercion, automatic callbacks, or Token output models. Consult
  `docs/canonical-runtime.md` and the canonical API gate for the removal inventory.
- Implement ideas independently. Do not edit installed dependencies in `vendor/`.
- Update relevant documentation and examples when public behavior changes. Add
  meaningful regression tests for changes to recognition, diagnostics, source
  round trips, or lifecycle contracts.

## Verification

Install development dependencies with `composer install` when needed. For code
changes, run the applicable checks and report any failures or unavailable tools:

```sh
composer validate --strict
composer check-platform-reqs
php -d memory_limit=512M vendor/pestphp/pest/bin/pest --do-not-cache-result
php vendor/phpstan/phpstan/phpstan analyse --configuration=phpstan.neon --memory-limit=512M
php tools/check-canonical.php
```

Run affected examples in `docs/examples/` when changing their documented behavior.
For formatting checks, use `make cs` and `make cs-check`, or the equivalent recipes
in `Makefile` if Make is unavailable. Existing style violations are not CI gates;
keep any cleanup scoped to the task. Documentation-only changes need review of
the diff and referenced paths, rather than the full runtime test suite.

In the handoff, state what changed, what was verified, and any remaining limitations.
