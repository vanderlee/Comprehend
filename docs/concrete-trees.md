# Immutable concrete trees

Source-based successful results expose `node`, the root of an immutable concrete
recognition tree. This is available on `ParseResult`, source-bound matches and
legacy coordinate adapters. Failed results and legacy string `match()` results
have a null node. A rejected `parseAll()` prefix does not expose its tree.

```php
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\Utf8Source;

$result = (new Sequence(new Text('é'), new Nothing()))
    ->parseAll(new Utf8Source('é'));
$result->node->rawText();                // 'é'
$result->node->children[0]->rawText();   // 'é'
$result->node->children[1]->rawText();   // ''
$result->node->children[1]->span->start; // 2
$result->node->children[1]->span->end;   // 2
```

`Tree\ConcreteNode` is a final readonly value with:

| Member | Meaning |
| --- | --- |
| `kind` | Fully qualified recognizing parser class |
| `span` | Original source and half-open canonical byte interval |
| `children` | Immutable list of concrete child nodes |
| `isTrivia` | Marks explicit trivia and accepted automatic spacing |
| `name`, `group`, `isTerminal` | Snapshotted token declarations for named projection |
| `rawText()` | Exact original bytes covered by the span |

See [Named AST projection](ast.md) for `ConcreteNode::ast()` and the cached
`ParseResult::ast()` view over named structure.

Nodes retain their source independently of mutable parser objects and lazy
semantic projections. Names, terminal-token flags, result transformations,
assignments and rule processors do not replace their raw text or remove their
children. Accessing the tree does not evaluate token or result callbacks.
Existing tokens, results and selected callback behavior remain available.

Terminals are leaves, including successful zero-width `Nothing`, `Start` and
`End`. Sequences and repetitions retain their successful children in grammar
order, including empty children. Choices retain only the selected alternative.
Directives and rule wrappers retain the wrapped child's concrete node, even
when a rule processor hides that child's semantic outputs. Successful negative
lookahead has an empty leaf; its speculative child is omitted. Exclusion retains
the accepted matching child and omits the exclusion probe.

`All` retains every successful required child in argument order. These spans
overlap, and a child may extend beyond the parent's shortest consumed span.
Accordingly, children must share source identity but need not partition or fit
inside the parent span. Do not reconstruct raw text by concatenating child text;
always use `rawText()` on the desired node.

Accepted legacy spacing now appears as marked trivia children in Sequence and
Repeat. See [Trivia retention and literal spacing](trivia.md) for explicit
boundary trivia and `lexeme()` scopes.
Trees on legacy coordinate adapters retain canonical byte spans even when token
and callback coordinates are converted to code points.

Custom source-compatible parsers should delegate children through
`parseInContext()` and return source-bound child matches through `success()`.
The internal `Success::setConcreteMatches()` hook can retain recognition children
separately from semantic output children. Tree construction rejects unbound
children rather than guessing their coordinates. No source is inferred for the
legacy string recognition path.
