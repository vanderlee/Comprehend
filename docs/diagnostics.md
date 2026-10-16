# Structured diagnostics

ParseResult separates consumed byte length from observed failure position.
`diagnostic` contains Source, `byteOffset`, `expected` labels and `rulePaths`.
Tied alternative failures retain distinct expected labels and paths. Named rules
add readable names. Text literal diagnostics use quoted literals; custom parsers
can override protected `diagnosticName()` and `diagnosticExpectation()`.

`location()` lazily indexes CRLF, lone CR and LF and returns one-based line/column.
UTF-8 columns count code points; byte-source columns count bytes. `excerpt()`
returns the original current line and `message()` formats expectations/location.
Indexing may read the whole source. Raw byte excerpts may not be valid UTF-8.

Within a parse session, unchanged failure details reuse the same Diagnostic
snapshot. New expectations, paths or failure positions produce a new snapshot;
retained results keep their original details. Speculative rollback invalidates
the cache when those details change, independently of the restored revision
counter. Ordinary and deferred-list diagnostics remain distinct until a failed
enclosing parse needs their merged details.

Negative-lookahead children, optional probes, successful exclusion probes and
successful repetition termination do not pollute expectations. Required failures,
guard rejection and relevant tied alternatives retain their details. Full-input
failure adds EOF at the accepted endpoint unless an observed failure is farther.
Successful results may retain useful observed failures; the presence of a
diagnostic alone does not mean the selected syntax failed.

Separated lists keep abandoned item/separator failure details separately from
successful termination diagnostics. If the enclosing recognition or complete-input
check fails, the farthest of these details and the ordinary failure is reported;
tied expectations and rule paths are combined. For example, `report\n[[1,\n?]]`
in the application-value comparison now reports the invalid item on line 3,
column 1 rather than the opening bracket on line 2. Successful prefixes and
complete lists do not expose these termination probes. Optional, repetition,
lookahead and recovery probe checkpoints also restore these deferred details.

Explicit [error recovery](recovery.md) retains each recovered region's original
failure separately. Use `diagnostics()` for that selected list and `hasRecovery()`
to distinguish recovered acceptance from ordinary acceptance. Synchronization
probe failures do not contribute expectations.
