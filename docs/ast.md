# Named AST projection

`Comprehend::named(string|int|callable $name, ?string $group = null, bool $leaf = false)` names
semantic structure. `ParseResult::ast()` caches an immutable projection of the
CST. Named branches retain nesting, source spans and empty nodes. Unnamed
containers promote their named descendants into a synthetic root spanning the
accepted input. Completely unnamed input still has a full-span synthetic root.
Explicit rule references name their grammar scope. Trivia and its descendants
are omitted. A semantic-leaf marker hides AST descendants while keeping the CST.
Subclasses of `Directive\Trivia` retain trivia classification, including in the
concrete node passed to a dynamic name callback. Their CST kind remains the
subclass name.

```php
$letter = (new \Vanderlee\Comprehend\Parser\Terminal\Text('é'))->name('letter');
$grammar = new \Vanderlee\Comprehend\Parser\Structure\Sequence(
    new \Vanderlee\Comprehend\Parser\Terminal\Text('('), $letter,
    new \Vanderlee\Comprehend\Parser\Terminal\Text(')')
);
$ast = $grammar->parseAll(new \Vanderlee\Comprehend\Source\Utf8Source('(é)'))->ast();
$ast->rawText();                     // (é), including unnamed punctuation
$ast->firstNamed('letter')->rawText();   // é
$ast->firstNamed('letter')->span->start; // 1
$ast->firstNamed('letter')->span->end;   // 3
```

AST reads do not evaluate transforms or commit effects. Later grammar naming
changes cannot alter an existing result. `rawText()` always references original
source, including punctuation/trivia inside the span, rather than concatenated
semantic output. AST and CST share [serialization operations](serialization.md).

## Navigation

`child(index)`, `firstChild()` and `lastChild()` return existing immutable child
nodes, or null when missing. Negative/out-of-range indices return null.
`firstNamed(string|int, ?group)` and `allNamed(string|int, ?group)` search immediate
children using strict name equality: integer 0 differs from string '0'. A null or
omitted group accepts any group. `firstInGroup(?group)` and `allInGroup(?group)`
search immediate children by exact group; null selects ungrouped children.
The `all` methods return lists in source order; no matches return an empty list.

`descendants()` lazily traverses every descendant in depth-first pre-order,
excluding the node itself. Direct-child helpers never search nested descendants.
Navigation neither evaluates semantic values nor commits effects, and it preserves
the original node identities, spans, raw text and serialized tree shape.

## Tags and dynamic names

`tag(string ...$tags)` adds unique nonempty tags on a new facade value. Concrete
nodes retain local tags. During AST projection, tags on unnamed containers flow
to their nearest named descendants; tags on a named node stay on that node.
Trivia is excluded from this propagation. AST and CST expose `tags` and
`hasTag()`. AST `firstTagged()` and `allTagged()` search immediate children.

A name callback receives the immutable ConcreteNode before its own name is
resolved, and returns string, integer or null (unnamed). It runs once per
successful recognition attempt, before guards, and must be pure: speculative
alternatives may invoke it. It cannot access semantic values or commit effects.
The returned name is fixed on the tree; navigation/serialization never rerun it.

```php
use Vanderlee\Comprehend\Comprehend;
use Vanderlee\Comprehend\Tree\ConcreteNode;

$token = Comprehend::range('a', 'z')->some()->lexeme()
    ->named(fn (ConcreteNode $node): string => 'word_' . $node->rawText())
    ->tag('word', 'token');
```

## Surrounding syntax

AST `preamble` and `postamble` are lists of source-bound Span fragments, including
unnamed punctuation and trivia around a named sibling. Within each named parent
(or synthetic root), a gap leads the next sibling; the final gap trails the last
sibling. Nested named parents own their inner gaps independently. These fragments
do not change a node's own span or `rawText()`.

Use `preambleText()`, `postambleText()` and `surroundedText()` to read the fragments.
For nonoverlapping siblings, concatenating their surrounded text reconstructs
their parent's input. Overlapping recognition branches do not form such a
partition; the parent's source span remains authoritative. Unnamed-only roots
retain their full raw text without manufacturing named children.
