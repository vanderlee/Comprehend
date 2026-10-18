# Upstream syntax-acceptance fixtures

These files contain test inputs and expected **syntax acceptance**, not decoded
values, query results, serialization or complete protocol behavior. Inputs are
Base64 encoded to retain exact bytes and line endings across platforms.

| File | Upstream | Revision | Cases |
| --- | --- | --- | --- |
| `yaml.json` | https://github.com/yaml/yaml-test-suite/tree/data | `6ad3d2c62885d82fc349026c136ef560838fdf3d` | 402 |
| `structured-fields.json` | https://github.com/httpwg/structured-field-tests | `781ba38464b2ec4c547e1e14d007d4f5ace7457e` | 1598 |
| `jsonpath.json` | https://github.com/jsonpath-standard/jsonpath-compliance-test-suite | `20ebdcce5008c8a777ee9c80ad853ecf606950b5` | 715 |

YAML entries retain each `in.yaml` and whether its directory contains `error`,
including variant subdirectories. The YAML suite's MIT license is retained in
`YAML-LICENSE`.

Structured-field entries retain `name`, `header_type`, `must_fail` and `can_fail`.
Multiple raw field values are joined with comma-space, as for HTTP field
combination. The upstream license is retained in `STRUCTURED-FIELDS-LICENSE.md`.

JSONPath entries retain the `name`, `selector` and `invalid_selector` fields from
`cts.json`. Only acceptance is tested: selecting the expected nodes requires a
JSONPath evaluator. The upstream license and notice are retained in
`JSONPATH-LICENSE` and `JSONPATH-NOTICE`.

No parser implementation from these projects is included.
