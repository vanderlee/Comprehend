# Contributing

Use PHP 8.2+ with DOM, JSON and mbstring. Install development dependencies with
Composer. Run platform checks, PHPUnit, the canonical API/syntax gate and examples:

```sh
composer validate --strict
composer check-platform-reqs
php vendor/phpunit/phpunit/phpunit --do-not-cache-result
php tools/check-canonical.php
php docs/examples/execution.php
php docs/examples/authoring.php
php docs/examples/file-input.php
php docs/examples/abnf.php
php docs/examples/release.php
php tools/release-notes.php 2.0.0
```

The PHPUnit configuration covers `src`; a coverage driver optionally writes
Clover output under `build/logs`. CI runs the checks on PHP 8.2–8.5, with an
experimental nonblocking PHP 8.6 job. Composer.lock is locally generated/ignored.

Custom parsers implement the [typed recognition contract](source-and-extension-contract.md).
Keep recognition/guards pure, spans in bytes, scope restoration exception-safe,
semantic projections cached and effects explicit. All authoring styles share the
same runtime. Add meaningful acceptance, diagnostics, source-round-trip and
lifecycle tests when changing those contracts. Do not reintroduce compatibility
adapters, old entry points, magic Ruleset lookup or Token output layouts; the removal
inventory and CI gate make this a breaking canonical API.
Magic dispatch is intentionally limited to Comprehend/Syntax authoring; it must
construct the existing typed parser graph and never enter the recognition runtime.

Source code from the supplied  snapshot is not copied. Its architectural
ideas are implemented independently; the review identifies defects to avoid.
Named graph authoring, freezing, safety checks and immutable facade composition
are documented in [Grammar authoring](grammar-authoring.md). File/performance
work is documented in [File input](file-input.md) and [Performance measurements](performance.md).

The [ABNF subset](abnf.md) generates that same runtime graph. Release verification
uses the existing test workflow through workflow_call; publishing requires the
reviewed 2.0.0 tag, a dated changelog and a passing required matrix. See
[Breaking-release preparation](breaking-release.md).

## Quality tools

Install development tools with `make install` (or `composer install`). GNU Make
and PHP must be on your PATH. Use `make test` for PHPUnit and `make phpstan` for
static analysis, initially configured at level 1 for `src` in `phpstan.neon`.
CI runs both on pull requests and pushes to `master`; the release workflow also
reuses these checks.

Use `make cs` for PHP_CodeSniffer's PSR-12 checks and `make cs-check` for a
PHP-CS-Fixer dry run against PHP-FIG PER Coding Style. PSR-12 is a partial style
check; the fixer uses its current `@PER-CS` preset. Both tools cover `src`,
`tests`, `tools`, `docs/examples` and the fixer configuration, excluding vendor
code and the supplied  snapshot. `make cs-fix` applies formatting; review
its diff before committing. Existing style violations are left for a separate
formatting cleanup, so style checks are local targets rather than CI gates.
`make qa` runs tests, PHPStan and both style checks.

On systems without Make, run the commands from the corresponding Makefile
recipes directly. Override the executables with
`make PHP=/path/to/php COMPOSER=/path/to/composer qa` when necessary.
