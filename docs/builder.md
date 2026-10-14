# Named grammar builders

Ruleset provides explicit `define(string, Comprehend|Parser|Definition|callable)`,
`ref(string, ...$arguments)`, `root(string|Comprehend|Parser)`, `defined(string)` and
`undefine(string)` operations. `rule(string, ...$arguments)` returns an immutable
Comprehend facade for fluent authoring; `ref()` returns a RuleReference parser for
low-level object constructors and direct recognition.
Rule lookup is deferred so forward/recursive definitions can be constructed in
any order. Undefined names raise LogicException on recognition; there is no
literal fallback or magic/static registry. Select the document parser explicitly
through `root()` and freeze to resolve/check its reachable graph.

```php
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Comprehend as C;

$grammar = new Ruleset([], 'example');
$grammar->define('twice', fn (C $item): C => $item->repeat(2, 2));
$grammar->root($grammar->rule('twice', C::text('x')));
$result = $grammar->freeze()->parse('xx');
```

Facade values supplied through constructor entries, `define()` or `root()` are
converted to detached Parser graphs at registration. Reusing a facade in several
Rulesets does not share mutable parser state with those builders.

Factories return Comprehend facade values; low-level Parser returns remain
supported for extensions. Invalid returns raise grammar errors. Factory arguments
are passed unchanged, so facade parameters can be typed as Comprehend.
Concrete Parser and facade rules take no factory arguments.
Definition accepts a facade, custom Parser or factory. `build(...$arguments)`
returns an immutable facade snapshot without evaluating factories; it supports
composition and parsing directly. Later Definition edits do not change an existing
build. Definition validators and processors retain the selected semantic lifecycle.
`Comprehend::of(Definition)` builds an unparameterized facade. `implementation()`
is an internal runtime boundary for low-level integration, not public authoring.
`Stub::setParser(Parser)` supplies explicit recursion for object grammars.

Each Ruleset owns its rules and cached unparameterized implementations.
Redefinition clears that instance's cache; existing results keep snapshots.
Parameterized factories are not cached across ordinary lookups. Frozen graphs
materialize references/factories per snapshot and remain independent of later
builder changes. See [Grammar authoring](grammar-authoring.md) for cache lifetime,
the immutable facade and graph safety, or [ABNF](abnf.md) for text authoring.
