# Contributing

Use PHP 8.1+ with JSON and mbstring. DOM is optional at runtime, but required for
the Pest suite and XML serialization tests. Install development dependencies with
Composer. Run platform checks, Pest, the canonical API/syntax gate and examples:

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

CI also runs `php -n -d extension=mbstring tools/check-without-dom.php` on Linux,
where DOM is a shared extension disabled by `-n`. This verifies parsing, semantic
values, AST/CST arrays and JSON, and the explicit XML dependency exception. The
standalone check does not need PHPUnit. Windows PHP builds may compile DOM in;
on those builds, `-n` cannot remove it and the check intentionally refuses to run.

The PHPUnit configuration covers `src`; a coverage driver optionally writes
Clover output under `build/logs`. CI runs the checks on PHP 8.1–8.5, with an
experimental nonblocking PHP 8.6 job. Composer.lock is locally generated/ignored.

Composer resolves dependencies against PHP 8.1.0 via `config.platform.php`, even
when run on a newer interpreter. PHPStan also targets 8.1. `check-platform-reqs`
checks the actual interpreter and extensions. After changing the minimum version,
refresh a locally generated lock file with `composer update` before testing that
version; an older lock/vendor directory may still contain PHP 8.2-only packages.

Custom parsers implement the [typed recognition contract](source-and-extension-contract.md).
Keep recognition/guards pure, spans in bytes, scope restoration exception-safe,
semantic projections cached and effects explicit. All authoring styles share the
same runtime. Add meaningful acceptance, diagnostics, source-round-trip and
lifecycle tests when changing those contracts. Do not reintroduce compatibility
adapters, old entry points, magic Ruleset lookup or Token output layouts; the removal
inventory and CI gate make this a breaking canonical API.
Magic dispatch is intentionally limited to Comprehend/Syntax authoring; it must
construct the existing typed parser graph and never enter the recognition runtime.

Named graph authoring, freezing, safety checks and immutable facade composition
are documented in [Grammar authoring](grammar-authoring.md). File/performance
work is documented in [File input](file-input.md) and [Performance measurements](performance.md).

The [ABNF subset](abnf.md) generates that same runtime graph. Release verification
uses the existing test workflow through workflow_call; publishing requires the
reviewed 2.0.0 tag, a dated changelog and a passing required matrix. See
[Breaking-release preparation](breaking-release.md).

## Documentation website

The GitHub Pages site builds the Markdown in `docs/` with MkDocs. Navigation and
link validation are configured in `mkdocs.yml`; add new pages to its navigation.
Use Python 3.12 and a separate environment for the documentation tools:

```sh
python -m venv .venv-docs
```

Activate it with `source .venv-docs/bin/activate` on Linux/macOS or
`.\.venv-docs\Scripts\Activate.ps1` in PowerShell, then run:

```sh
python -m pip install -r requirements-docs.txt
python -m mkdocs serve
```

Open the address printed by MkDocs to preview the site. Before submitting, run
`python -m mkdocs build --strict`. Missing page targets and anchors fail the build;
external URLs are not fetched. Generated files go into the ignored `site/` folder.
Link PHP example source to GitHub so readers can view it without downloading it.

The Documentation workflow validates pull requests and publishes pushes to
`master`. To enable the first deployment, a repository maintainer must select
**Settings > Pages > Build and deployment > Source > GitHub Actions**. The
`github-pages` environment should allow deployments from `master` only. A manual
workflow run on `master` can publish again without a new commit. No `gh-pages`
branch is needed.

## Quality tools

Install development tools with `make install` (or `composer install`). GNU Make
and PHP must be on your PATH. Use `make test` for Pest and `make phpstan` for
static analysis, configured at level 10 for `src` in `phpstan.neon`.
CI runs both on pull requests and pushes to `master`; the release workflow also
reuses these checks.

Use `make cs` for PHP_CodeSniffer's PSR-12 checks and `make cs-check` for a
PHP-CS-Fixer dry run against PHP-FIG PER Coding Style. PSR-12 is a partial style
check; the fixer uses its current `@PER-CS` preset. Both tools cover `src`,
`tests`, `tools`, `docs/examples` and the fixer configuration, excluding vendor
code. `make cs-fix` applies formatting; review
its diff before committing. Existing style violations are left for a separate
formatting cleanup, so style checks are local targets rather than CI gates.
`make qa` runs tests, PHPStan and both style checks.

On systems without Make, run the commands from the corresponding Makefile
recipes directly. Override the executables with
`make PHP=/path/to/php COMPOSER=/path/to/composer qa` when necessary.

## Test layout

Keep tests focused on a single class or trait in `tests/src/`. Put feature tests
that cover interactions across classes, runtime contracts, or complete grammars
in `tests/features/`. Shared test infrastructure stays in `tests/`. Pest
discovers both directories recursively.

Write tests with Pest's `test()` closures and attach parameterized cases with
`with()`. Pest 2 preserves the library's PHP 8.1 development baseline. Existing
shared assertions and stateful fixtures use abstract PHPUnit base classes selected
with `uses()`; dataset providers are static and run before test setup. Run the
suite through Pest, including when selecting a single file or using `--filter`.

Every class under `Vanderlee\Comprehend` must have a focused unit test at the
matching path in `tests/src/` (for example, `src/Source/ByteSource.php` has
`tests/src/Source/ByteSourceTest.php`). Test abstract classes through concrete
test doubles and cover their shared contracts. Keep cross-class scenarios in
the feature suite rather than replacing them with class tests.

Group feature tests by behavior:

- `authoring/`: grammar construction, facade composition, syntax dispatch, and ABNF.
- `parsing/`: runtime contracts, consumption, diagnostics, limits, and trivia.
- `sources/`: source coordinates, file input, source modes, and regex windows.
- `trees/`: concrete trees, ASTs, navigation, metadata, and serialization.
- `semantics/`: lazy values, validation, and explicit commit effects.
- `examples/`: complete example grammars and their local helpers.
