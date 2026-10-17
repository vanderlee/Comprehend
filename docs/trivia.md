# Explicit trivia policy

`Sequence::spacing(?Parser $trivia)` and `Repeat::spacing(?Parser $trivia)` define
required intervening trivia. Use `Repeat::star(...)` if that trivia is optional.
Omitting the policy inherits the current scope; `spacing(null)` disables it.
`Space(?Parser, Parser)` applies a scoped override to a subtree. No boolean
spacing toggles or independent legacy spacing runtime remains.

Intervening trivia is recognized between grammar items, including zero-width
items, with the owning session's case/choice policy. Automatic spacing is
suppressed inside its own trivia grammar to prevent recursive insertion.
Successful repetition termination discards speculative trivia and its effects.
Leading and trailing trivia are explicit boundary parsers:

```php
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;

$trivia = Repeat::star(new Text(' '))->trivia();
$body = (new Sequence(new Text('a'), new Text('b')))->spacing($trivia);
$document = (new Sequence($trivia, $body, $trivia))->spacing(null);
```

`lexeme()` suppresses automatic trivia throughout a subtree, including nested
local overrides. Literal whitespace still matches normally. Accepted trivia is
marked in the immutable CST, omitted from named ASTs, and included in validation
and explicit commit. Automatic trivia does not contribute named collections.
Scope restoration is exception-safe. Source round trips always use exact spans.

Automatic trivia wrappers are created lazily and reused by parser identity within
one parse session, including across nested spacing scopes. Each attempt still
creates an independent match with its own spans, lazy values, validation and
commit state. No wrapper is created for spacing suppressed by `lexeme()`, and
the cache is not shared between sessions or stored in the grammar graph.
