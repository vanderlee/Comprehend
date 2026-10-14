# Named grammar builders

Ruleset provides explicit `define(string, Comprehend|Parser|Definition|callable)`,
`ref(string, ...$arguments)`, `root(string|Comprehend|Parser)`, `defined(string)` and
`undefine(string)` operations. `rule()` delegates to `ref()`.
Rule lookup is deferred so forward/recursive definitions can be constructed in
any order. Undefined names raise LogicException on recognition; there is no
literal fallback or magic/static registry. Select the document parser explicitly
through `root()` and freeze to resolve/check its reachable graph.

```php
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Terminal\Text;

$grammar = new Ruleset([], 'example');
$grammar->define('twice', fn (Parser $item): Parser => Repeat::exact($item, 2));
$grammar->root($grammar->ref('twice', new Text('x')));
$result = $grammar->freeze()->parseAll(new \Vanderlee\Comprehend\Source\Utf8Source('xx'));
```

Facade values supplied through constructor entries, `define()` or `root()` are
converted to detached Parser graphs at registration. Reusing a facade in several
Rulesets does not share mutable parser state with those builders.

Factories must return Parser. Concrete Parser and facade rules take no factory arguments.
Definition wraps a Parser or factory, with `build(...$arguments)` for explicit
instantiation, selected ParseResult validators and cached collection processors.
`Stub::setParser(Parser)` supplies explicit recursion for object grammars.

Each Ruleset owns its rules and cached unparameterized implementations.
Redefinition clears that instance's cache; existing results keep snapshots.
Parameterized factories are not cached across ordinary lookups. Frozen graphs
materialize references/factories per snapshot and remain independent of later
builder changes. See [Grammar authoring](grammar-authoring.md) for cache lifetime,
the immutable facade and graph safety, or [ABNF](abnf.md) for text authoring.
