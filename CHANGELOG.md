# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## 2.0.0 - Unreleased

### Added

- Explicit byte and UTF-8 sources, immutable source spans, and structured parse diagnostics.
- Concrete syntax trees, named AST projection, and cached typed semantic values.
- Named grammar references, frozen graphs, immutable fluent authoring, and graph safety checks.
- Seekable file snapshots, incremental UTF-8 validation, bounded literal regex matching, and optional parse budgets.
- An ABNF authoring subset with explicit local choice policies, case flags, numeric terminals, and core rules.

### Changed

- PHP 8.2 or newer is required, with DOM, JSON, and mbstring.
- Parse offsets and lengths use bytes. Prefix and complete-input parsing are explicit.
- Callbacks and assignments run through explicit, idempotent commit after semantic validation.
- Syntax trees serialize directly to the documented array, JSON, and XML schemas.

### Removed

- Legacy string matching, magic grammar lookup, implicit argument coercion, and coordinate adapters.
- The separate Token output model and automatic callback execution.
- Compatibility shims for the previous API. See the [breaking-release guide](docs/breaking-release.md) for caller changes.

## 1.0.2 - 2024-04-11
### Changed
- PHP 8+ compatibility; renamed `Match` to `AbstractMatch`.
- Style improvements.

## 1.0.1 - 2018-09-27
### Changed
- Renamed `kleene` constructor in `Repeat` parser to `star`.

## 1.0.0 - 2018-09-26
### Changed
- Major refactoring and project rename
