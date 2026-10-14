# Remaining work

The [evolution plan](docs/comprehend--review.md) is the authoritative backlog.
Phase 2A's runtime cleanup is implemented; the [removal inventory](docs/canonical-runtime.md)
records the supported replacement for each removed API.

- Phase 3: explicit named graph API, graph freezing, immutable fluent composition,
  static safety analysis and authoring equivalence examples.
- Phase 4: measured performance baselines, bounded file input, incremental encoding
  validation, depth/work budgets and evidence-driven optional memoization.
- Phase 5: optional ABNF frontend, final documentation and breaking release.
- Review bundled RFC assets for conformance beyond existing acceptance fixtures.

Backwards compatibility, legacy runtime adapters and old Token output shapes are
not backlog items or release requirements.