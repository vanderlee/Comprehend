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

Negative-lookahead children, optional probes, successful exclusion probes and
successful repetition termination do not pollute expectations. Required failures,
guard rejection and relevant tied alternatives retain their details. Full-input
failure adds EOF at the accepted endpoint unless an observed failure is farther.
Successful results may retain useful observed failures; the presence of a
diagnostic alone does not mean the selected syntax failed.

Explicit [error recovery](recovery.md) retains each recovered region's original
failure separately. Use `diagnostics()` for that selected list and `hasRecovery()`
to distinguish recovered acceptance from ordinary acceptance. Synchronization
probe failures do not contribute expectations.
