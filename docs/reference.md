# Reference

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
