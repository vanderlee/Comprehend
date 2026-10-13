# Recognition, validation and effect commit

Canonical `matchPrefix()`, `parseSource()` and `parseAll()` now return results
without executing ordinary callbacks or assignments. Recognition builds source
spans and concrete structure; semantic reads and validation follow acceptance;
effects execute only through `ParseResult::commit()`.

```php
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Source\Utf8Source;
use Vanderlee\Comprehend\Tree\ConcreteNode;

$assigned = null;
$parser = (new Text('42'))
    ->guard(fn (ConcreteNode $node): bool => $node->rawText() !== '00')
    ->mapValue(fn (ParseResult $result): int => (int) $result->rawText())
    ->validateSemantic(fn (ParseResult $result) => $result->value() < 100
        ? true : 'Number must be less than 100')
    ->assignTo($assigned);
$result = $parser->parseAll(new Utf8Source('42'));
$assigned; // null: recognition does not commit assignments
$result->validate()->valid; // true
$result->value(); // int 42, cached
$result->commit(); // true: validates, then performs accepted effects once
$assigned; // '42': existing assignments still receive raw text
$result->commit(); // true: idempotent, no duplicate callbacks
```

## Pure recognition guards

`guard(callable)` receives an immutable ConcreteNode for a successful candidate.
A false predicate rejects that candidate, allowing choice to try another
alternative. Rejection contributes a `guard(rule)` diagnostic at the candidate
end. Guards run on speculative attempts and must be pure: inspect spans, original
text and CST children, without semantic transformations or external effects.
Purity is a caller contract, not a sandbox. Thrown guard errors propagate as
programming errors. Guards run on source recognition, including source-based
compatibility adapters; legacy string recognition does not apply them.

## Selected semantic validation

`validateSemantic(callable)` registers a validator receiving the accepted local
ParseResult. Validators must return true, false, or an error string. False adds
`Semantic validation failed`; strings add the supplied error. They run only when
`validate()` or `commit()` is requested on a syntactically accepted result, after
the selected document tree and EOF policy have been established. Required child
scopes (including spacing, processed rules and intersections) validate before
their enclosing scope. Discarded probes and alternatives do not validate.

`validate()` returns a cached immutable ValidationResult with `valid` and
`errors`. Semantic rejection leaves syntactic `success`, source spans, CST and
AST intact and does not retry grammar alternatives. Syntax failures return an
invalid report without running validators. Validator exceptions are cached and
rethrown; recursive validation throws LogicException. Validator lists are
snapshotted per recognition. Semantic reads used by validators remain cached.

On the canonical path, existing Definition validators retain their `(text,
results)` signatures but now run as selected-scope semantic validators after
syntax. Definition result transforms and processors are evaluated lazily,
rather than to inspect abandoned candidates. To reject alternatives during
recognition, move a syntactic condition into a pure `guard()`. New semantic
validators should be pure too; external side effects cannot be rolled back.

## Explicit effects

`commit()` first validates the selected result and returns false if syntax or
semantics rejects it. Otherwise, it executes existing selected callbacks and
assignments in their established child/spacing/parent order and returns true.
Callbacks and assignment lists are snapshotted per recognition. `isCommitted()`
reports successful completion. Repeated successful commits are no-ops; the
low-level legacy `Success::resolve()` still rejects repeated resolution.

If an effect throws, commit stops, retains that exception and rethrows it on
future attempts without replaying the effect sequence. `isCommitted()` remains
false. Earlier external effects may already have occurred: this is at-most-once
execution, not a transaction or rollback mechanism. Recursive commit throws
LogicException. Failed roots, rejected prefixes and abandoned alternatives
never commit effects through the canonical lifecycle.

## Compatibility timing

Legacy string `match()`/`__invoke()`, `matchSource()` and LegacyParserAdapter
continue to resolve ordinary callbacks automatically on successful acceptance.
Their Definition validators keep the old recognition-time timing and may read
transforms speculatively. The adapter's full-input API still checks EOF before
ordinary callbacks. New semantic validators are part of the explicit canonical
lifecycle; automatic legacy resolution does not run them. Wrapping an already
resolved legacy match in ParseResult cannot undo effects already performed.

For migration, replace `$parser->matchSource($source)` with
`$parser->matchPrefix($source)` and call `commit()` after reviewing syntax,
semantic validation and projections. Use `parseAll()` when document acceptance
requires EOF. Application code that previously expected assignments immediately
after canonical parsing must now commit explicitly.
