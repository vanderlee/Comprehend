# Optional syntax-tree retention

AST projection is already lazy: `ast()` constructs it only when requested.
CST construction is enabled by default. If an application needs only extracted
values, pass `trees: false` to skip CST construction and retention too:

```php
use Vanderlee\Comprehend\Extensions\Binding;

$syntax = Binding::syntax();
$numbers = $syntax->integer()->mapText('intval')->separatedBy(',')->between('[', ']');
$grammar = $numbers->freeze();
$result = $grammar->parse('[1,2]', trees: false);
$result->value();   // [1, 2], lazy and cached
$result->rawText(); // '[1,2]'
$result->node;      // null, even though recognition succeeded
$result->ast();    // null; no tree is reconstructed later
```

The option is available on facade and frozen `parse()`, `parseAll()` and
`matchPrefix()`, and low-level Parser `parseAll()` and `matchPrefix()`. It applies
to one recognition session, not the grammar or source. Reusing the same frozen
grammar later without the option builds trees normally. Facade per-call spacing
and all existing positional arguments retain their meanings; prefer the named
argument for this option.

## Retained behavior

Recognition still uses the same typed parser graph and runtime. Byte spans,
source access, raw text, failures and diagnostics remain available. Values,
named collections, semantic validation and explicit effects retain their usual
lazy caches and selected-child ordering. Recovery retains its regions and
diagnostics, including recovered values, without synthetic error tree nodes.
Depth/work budgets and parser-entry metrics keep their existing meanings.

This is not constant-memory streaming. Results retain their source, selected
execution records, caches and extracted values. Disabling trees avoids their
construction; it does not disable source validation or application callbacks.

## Tree-dependent grammars and callbacks

Guards receive concrete nodes, and dynamic naming callbacks receive temporary
concrete nodes. A grammar declaring either is rejected with `LogicException`
before recognition through public parse entry points when trees are disabled.
The dependency check is conservative across all declared branches. Fixed names,
groups, leaf flags and tags are inactive metadata when there is no tree.

PHP cannot determine whether an arbitrary callback or later application code
will inspect a tree. When opting out, ensure that `mapValue()`, validators,
effects, processors and custom callbacks use available values, spans or text.
Their ParseResults have `node === null` and `ast() === null`. A callback that
requires trees must use the default mode; no fake tree or lazy reparse is supplied.
Mapping and effect errors retain their normal cached-error lifecycle.

Custom recognizers inspect `ParseSession::treesEnabled()` before constructing
optional tree nodes. Override `Parser::requiresTree(): bool` when recognition
itself needs child CSTs, combining the parent requirement with your own. Declare
dependencies through `children()` as usual. The checked runtime also rejects a
tree-dependent parser supplied dynamically in a tree-free session. Direct
ParseSession callers can pass `trees: false` to its constructor.

Use the [extraction profiler](extraction-profiling.md) with `--without-trees`
to inspect this mode. Its tracing regression checks extracted values and
parser work and verifies that no core ConcreteNode objects are allocated.
