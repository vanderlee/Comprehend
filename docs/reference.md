# Reference

See [Execution behavior and limitations](execution.md) for prefix matching,
complete ASCII input checks, choice selection, greedy repetition, recursion,
and the current Unicode limitation.

The phase 1.1 [source coordinate and extension contract](source-and-extension-contract.md)
specifies byte spans, encoding modes and compatibility policies. Independent
[in-memory sources and spans](sources.md) and explicit
[source-based matching](source-matching.md) are available, together with
[canonical results and legacy coordinate adapters](parse-results.md).

See [Prefix and full-input entry points](consumption.md) for `matchPrefix()`,
`parseAll()`, empty input and explicit trailing-trivia policies.

## Arguments
There are 

## Terminal parsers

### Any
Matches any single character.

### Char
### End
### Nothing
### Range
### Regex
### Set
### Start
### Text

## Scoped directives and parser options

Spacing, case sensitivity, and choice preference overrides apply only within
their parser or directive. The previous context is restored after success,
failure, or an exception, including nested overrides. Exceptions propagate to
the caller after restoration.

Spacing recognition defers callbacks and `assignTo()` until the enclosing
successful root resolves them through `match()`, like ordinary child callbacks.
Accepted spacer callbacks run before the following child's callbacks. Failed
alternatives, failing parents, and spacing before a failed repeat iteration
produce no spacing effects. Successful zero-length spacers still run their
callbacks when accepted; spacers retain their independent default context.

Spacing stays out of token children and named result collection. Processed
rules retain selected child callbacks even when their processors replace child
outputs. `Context::skipSpacing()` no longer resolves effects itself; custom
parsers using it must capture its optional third argument and attach that match
with `Success::addPrecedingMatch()` to the following accepted child.

## Structure parsers

### Repeat

`Repeat($parser, $min = 0, $max = null)` greedily repeats a child parser.
Bounds must be nonnegative, and a finite maximum must be at least the minimum.
`null` means no maximum. A maximum of zero succeeds without invoking the child.

Every successful child match must consume input. A successful zero-length match
throws `LogicException`, even for bounded repetition or when the minimum has
already been met. This applies to nullable parsers, successful lookahead, and
custom parsers. Skipped spacing does not satisfy the child progress requirement.
A failed child match ends repetition normally; the repetition succeeds if its
minimum has been met. Use lookahead outside repetition, or repeat a sequence
that also consumes input.
