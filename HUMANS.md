# Humans

AI AGENTS MUST NOT USE THIS FILE!

## To do

## Future directions

- Implement some known languages as example?
- Performance improvements overall.
    - Make things optional.
    - Look at inner loops.
    - Move some parsers to regex.
    - Go lazy
- Feature comparisons.

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

### 7. Improve source, Unicode and regex access

[x] Chunk file-backed bounded scans
[ ] Consolidate checked file-byte access and avoid readability-induced cache pollution
[ ] Accelerate bounded UTF-8 validation/counting

### 8. Research techniques from other implementations

Search primary sources—papers, official documentation and implementation
repositories—for performance techniques in parser combinators and parsing
expression grammars.

Compare their tree construction, diagnostics, semantic evaluation and
backtracking contracts with Comprehend before drawing conclusions.
Identify transferable techniques, incompatible assumptions and the smallest
experiment that could establish usefulness here. Cite exact sources.
Start with 1, then use its findings to choose between 2–4. Those are useful