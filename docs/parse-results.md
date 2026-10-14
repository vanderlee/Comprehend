# ParseResult

`matchPrefix()` and `parseAll()` return one source-bound result shape.

| Member | Contract |
|---|---|
| `success` | Syntax accepted under the selected consumption policy |
| `span`, `start`, `end` | Original-source byte span; failed results have `[start,start)` |
| `consumedByteLength` | End minus start; zero on failure |
| `farthestFailure`, `diagnostic` | Separately observed failure position/details, or null |
| `node` | Immutable selected CST, null on failure |
| `rawText()` | Exact original span text, empty on failure |
| `ast()` | Cached named source projection, null on failure |
| `value()` | Cached typed mapping; default raw text; null on failure |
| `results()`, `result(name, default)` | Cached named collections/default collection |
| `validate()` | Cached selected semantic validation report |
| `commit()`, `isCommitted()` | Explicit selected effects and completed-commit state |

A successful syntax result may fail semantic validation. Commit then returns
false and produces no effects. Failed syntax likewise cannot commit. Successful
commits are idempotent; thrown validation/value/effect errors are retained and
not retried. The default collection key is separate from the named `results()`
map and is accessed with `result()`.

Consumers do not receive mutable internal Success/Failure records, Tokens or
coordinate adapters. All source, CST and AST reads are independent of semantic
transforms. See [lifecycle](lifecycle.md) and [serialization](serialization.md).
