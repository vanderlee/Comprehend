# Humans

AI AGENTS MUST NOT USE THIS FILE!

## To do

## Future directions

- Implement some known languages as example?
- Performance improvements overall.
    - Make things optional.
    - Look at inner loops.
    - Move some parsers to regex.
- Feature comparisons.
- Add solid test coverage.

## Ongoing concerns

- Benchmark and improve performance
- Maintain Library

## AI performance prompts

### Generic

Research performance opportunities in the current Comprehend checkout.
Follow AGENTS.md and read the relevant implementation, tests and performance
documentation. Treat historical measurements as leads requiring verification.

Preserve the shared typed runtime, byte offsets, immutable CSTs, diagnostics,
pure recognition, cached semantics, explicit effects and execution limits.

Return:

- Findings with file and method references.
- What is already optimized.
- Up to three remaining opportunities, ranked by expected impact.
- A reproducible experiment for each, including correctness checks.
- Memory, preparation-time and complexity tradeoffs.
- Evidence that would make you reject the proposed optimization.

Distinguish measured findings from hypotheses. Do not change production code.

### 5. Reduce repeated recognition and dispatch

[x] Specialize compiled-literal failure handling
[ ] Add conservative choice dispatch, then compare a literal trie with prefix factoring
[ ] Instrument selective memoization

### 6. Improve cold parsing and grammar preparation

Investigate graph copying, reference resolution, safety analysis, optimizer
preparation and reusable frozen snapshots.

Compare one-shot facade parsing with repeated facade and explicit frozen
parsing. Determine the parse count needed to amortize optimization costs.
Identify preparation work that could be avoided, shared or delayed without
making mutable definitions stale or sharing recognition state.

### 7. Improve source, Unicode and regex access

Investigate ByteSource, Utf8Source, file paging, readUnit, boundary checks,
regex subjects and bounded scanning.

Measure costs across ASCII, multibyte UTF-8 and large files. Look for repeated
validation, copying, materialization or page-boundary work beyond existing
subject caching and bulk literal matching.

Preserve byte spans, Unicode behavior, stable file snapshots and bounded
memory guarantees. Include adversarial and boundary inputs.

### 8. Research techniques from other implementations

Search primary sources—papers, official documentation and implementation
repositories—for performance techniques in parser combinators and parsing
expression grammars.

Compare their tree construction, diagnostics, semantic evaluation and
backtracking contracts with Comprehend before drawing conclusions.
Identify transferable techniques, incompatible assumptions and the smallest
experiment that could establish usefulness here. Cite exact sources.
Start with 1, then use its findings to choose between 2–4. Those are useful