# Named AST projection

`ParseResult::ast()` lazily projects the immutable concrete tree into named
structure. It returns a cached `Tree\AstNode` on success and null on failure,
including a prefix rejected by `parseAll()`. `ConcreteNode::ast()` performs the
same projection directly, returning a fresh tree; source matches and coordinate
adapters can use `$match->node->ast()`.

Names come from existing `token($name, $group, $isTerminal)` declarations,
including named Ruleset rules. These declarations are snapshotted into the CST
during recognition. Later parser mutations cannot rename an existing AST.
Existing token serialization remains available independently.

```php
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\Utf8Source;

$pair = (new Sequence(
    (new Text('é'))->token('letter'),
    ',',
    (new Text('a'))->token('letter')
))->token('pair');
$result = (new Sequence('(', $pair, ')'))->parseAll(new Utf8Source('(é,a)'));
$ast = $result->ast();
$ast->name; // null: synthetic root for the unnamed outer sequence
$ast->rawText(); // '(é,a)', including punctuation
$ast->children[0]->name; // 'pair'
$ast->children[0]->rawText(); // 'é,a'
$ast->children[0]->children[0]->span->start; // 1
$ast->children[0]->children[0]->span->end; // 3: byte coordinates
```

Projection follows these rules:

- Named nodes retain their group, original span and projected children.
- Unnamed containers promote their named descendants in grammar order.
  Nested named branches retain their nesting; repeated names stay separate list
  entries rather than overwriting one another.
- A named root is returned directly. An unnamed root always has a synthetic
  node with null name/group and the entire root span, even if there is only one
  named descendant or no named input at all. Completely unnamed input therefore
  has an empty child list and intact raw source.
- Trivia subtrees are omitted, including named descendants. A trivia-only root
  produces an empty synthetic root retaining its consumed source span.
- Terminal naming flags stop projection below that node. CST children remain
  intact. An unnamed terminal-marked container contributes no named descendants.
- Empty named matches remain nodes with valid zero-width spans. Failed and
  discarded alternative matches never contribute AST nodes.

`AstNode` is final and readonly, with `name` (string, integer or null), `group`,
`span`, `children` and `rawText()`. CST naming fields are `name`, `group` and
`isTerminal`; naming never changes the CST's recognition children or raw text.
All node positions remain canonical bytes on legacy coordinate adapters too.

Raw source is separate from semantic content. AST projection never executes
token/result transformations or assignments, and processors do not remove its
recognition structure. Use `result()` and `results()` for current semantic
outputs, or [cached typed semantic values](semantic-values.md) for `value()`.
Use root `rawText()` for
source reconstruction, rather than concatenating AST leaves: punctuation,
unnamed text and trivia may be absent from the named structure. Intersection
(`All`) children can overlap and extend beyond their parent's consumed span,
as in the CST.
