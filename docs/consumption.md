# Prefix and full-input parsing

```php
$parser = new \Vanderlee\Comprehend\Parser\Terminal\Text('a');
$source = new \Vanderlee\Comprehend\Source\Utf8Source('ab');
$parser->matchPrefix($source)->success; // true, consumes one byte
$parser->parseAll($source)->success;    // false, consumes zero bytes
```

Both calls accept an explicit byte offset. `parseAll()` requires EOF at the end
of the accepted match, consuming the remainder from that offset, not necessarily
from zero. Completion is checked before semantic mapping, validation or commit.
Failures retain farthest observed diagnostics and zero-width source spans.
Negative/out-of-range positions and interior UTF-8 positions are argument errors.

Leading/trailing trivia must be recognized explicitly by the grammar.
`spacing()` adds intervening trivia only. No entry point auto-skips boundaries
or auto-commits effects. A successful first choice is not retried merely because
full-input completion fails; use explicit longest choice or factor the grammar.
See [execution](execution.md), [trivia](trivia.md) and runnable examples.
