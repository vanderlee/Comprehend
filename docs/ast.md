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
$ast->children[0]->rawText();        // é
$ast->children[0]->span->start;      // 1
$ast->children[0]->span->end;        // 3
```

AST reads do not evaluate transforms or commit effects. Later grammar naming
changes cannot alter an existing result. `rawText()` always references original
source, including punctuation/trivia inside the span, rather than concatenated
semantic output. AST and CST share [serialization operations](serialization.md).
