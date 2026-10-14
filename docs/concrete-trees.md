# Immutable concrete syntax trees

Every successful ParseResult has `node`, an immutable ConcreteNode. Its fields
are `kind`, `span`, `children`, `isTrivia`, `name`, `group` and `semanticLeaf`.
Children are an ordered list of nodes from the same source. Spans and original
source references are immutable. Empty nodes have valid zero-width spans.

Sequence and repetition retain accepted syntax and intervening trivia. Choice
retains its selected alternative; lookahead has an empty node; exclusion retains
the required accepted child. Intersection retains overlapping recognition
children, even when some cover a larger interval than the parent's shortest
accepted match. Every required intersection child contributes its selected\ncollections, validation and effects, in grammar order. Do not reconstruct overlapping trees by concatenating children:
`rawText()` always slices the node's own source span.

Directives and explicit references wrap their children without mutating child
nodes or lifecycle caches. Definition processors change semantic collections,
not CST structure. Names and semantic-leaf markers affect only the named AST;
they do not drop concrete children or trivia. There are no token transparency or
child-index layouts. Serialize the tree directly using
[the canonical schema](serialization.md).
