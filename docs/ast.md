# Named AST projection

`Parser::name(string|int $name, ?string $group = null, bool $leaf = false)` names
semantic structure. `ParseResult::ast()` caches an immutable projection of the
CST. Named branches retain nesting, source spans and empty nodes. Unnamed
containers promote their named descendants into a synthetic root spanning the
accepted input. Completely unnamed input still has a full-span synthetic root.
Explicit rule references name their grammar scope. Trivia and its descendants
are omitted. A semantic-leaf marker hides AST descendants while keeping the CST.

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
