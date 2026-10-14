# Named grammar builders

Ruleset provides explicit `define(string, Parser|Definition|callable)`,
`rule(string, ...$arguments)`, `defined(string)` and `undefine(string)` operations.
Rule lookup is deferred so forward/recursive definitions can be constructed in
any order. Undefined names raise LogicException on recognition; there is no
literal fallback or magic/static registry. Set `Ruleset::ROOT` to a Parser when
using the Ruleset itself as the document parser.

```php
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Terminal\Text;

$grammar = new Ruleset([], 'example');
$grammar->define('twice', fn (Parser $item): Parser => Repeat::exact($item, 2));
$grammar->define(Ruleset::ROOT, $grammar->rule('twice', new Text('x')));
$result = $grammar->parseAll(new \Vanderlee\Comprehend\Source\Utf8Source('xx'));
```

Factories must return Parser. Concrete Parser rules take no factory arguments.
Definition wraps a Parser or factory, with `build(...$arguments)` for explicit
instantiation, selected ParseResult validators and cached collection processors.
`Stub::setParser(Parser)` supplies explicit recursion for object grammars.

Each Ruleset owns its rules and cached unparameterized implementations.
Redefinition clears that instance's cache; existing results keep snapshots.
Parameterized factories are not cached across lookups. Graph freezing and the
new `ref`/`root` graph API belong to Phase 3, as does immutable fluent composition.
