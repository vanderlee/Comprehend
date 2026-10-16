# ParseResult

`matchPrefix()` and `parseAll()` return one source-bound result shape.

| Member | Contract |
|---|---|
| `success` | Syntax accepted under the selected consumption policy |
| `span`, `start`, `end` | Original-source byte span; failed results have `[start,start)` |
| `consumedByteLength` | End minus start; zero on failure |
| `farthestFailure`, `diagnostic` | Separately observed failure position/details, or null |
| `hasRecovery()`, `recoveries()` | Whether explicit recovery occurred, and selected source-bound recovery records |
| `diagnostics()` | Selected recovery diagnostics on acceptance, or the terminal diagnostic on failure |
| `node` | Immutable selected CST, null on failure or when `trees: false` |
| `rawText()` | Exact original span text, empty on failure |
| `ast()` | Cached named source projection, null on failure or when `trees: false` |
| `value()` | Cached typed mapping; default raw text; null on failure |
| `results()`, `result(name, default)` | Cached named collections/default collection |
| `validate()` | Cached selected semantic validation report |
| `commit()`, `isCommitted()` | Explicit selected effects and completed-commit state |

A successful syntax result may fail semantic validation. Commit then returns
false and produces no effects. Failed syntax likewise cannot commit. Successful
commits are idempotent; thrown validation/value/effect errors are retained and
not retried. The default collection key is separate from the named `results()`
map and is accessed with `result()`.

With explicit [error recovery](recovery.md), acceptance can include malformed
regions. Such results retain a partial tree when trees are enabled, fail validation before semantic
validators execute, and cannot commit. A directly recovered parser's default
value is a `Recovery` record rather than its operand's usual value.

Use [optional tree retention](tree-retention.md) for value-only parsing without
CST allocation. Successful results can then have no tree while retaining source
spans, diagnostics, values and the ordinary validation/effect lifecycle.

Consumers do not receive mutable internal Success/Failure records, Tokens or
coordinate adapters. All source, CST and AST reads are independent of semantic
transforms. See [lifecycle](lifecycle.md) and [serialization](serialization.md).
