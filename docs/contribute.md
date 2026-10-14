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
adapters, old entry points, magic dispatch or Token output layouts; the removal
inventory and CI gate make this a breaking canonical API.

Source code from the supplied  snapshot is not copied. Its architectural
ideas are implemented independently; the review identifies defects to avoid.
Named graph authoring, freezing, safety checks and immutable facade composition
are documented in [Grammar authoring](grammar-authoring.md). File/performance
work is documented in [File input](file-input.md) and [Performance measurements](performance.md).

The [ABNF subset](abnf.md) generates that same runtime graph. Release verification
uses the existing test workflow through workflow_call; publishing requires the
reviewed 2.0.0 tag, a dated changelog and a passing required matrix. See
[Breaking-release preparation](breaking-release.md). Formatting tools are not
bundled; check new/changed PHP manually against PHP-FIG PER Coding Style when
no configured formatter is available, without rewriting unrelated code.
