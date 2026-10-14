# Contributing

Use PHP 8.2+ with DOM, JSON and mbstring. Install development dependencies with
Composer. Run platform checks, PHPUnit, the canonical API/syntax gate and examples:

```sh
composer validate --strict
composer check-platform-reqs
php vendor/phpunit/phpunit/phpunit --do-not-cache-result
php tools/check-canonical.php
php docs/examples/execution.php
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
New graph authoring/freezing work is Phase 3; file/performance work is Phase 4.
