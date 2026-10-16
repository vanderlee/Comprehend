# Recognition, semantics and effect commit

1. Pure recognition and `guard(callable(ConcreteNode): bool)` choose syntax.
   Guards may run on speculative alternatives; they cannot read typed semantic
   output from a CST node. False rejects the attempt.
2. Successful ParseResult reads evaluate `mapValue()` or collection operations
   lazily, with per-recognition caching. Failed/discarded attempts do not evaluate
   them automatically. Raw CST/AST text never depends on transforms.
3. `validate()` runs selected semantic validators after syntactic acceptance.
   Validators receive ParseResult and return true, false or an error string.
   Child and accepted-trivia validation contribute errors once. Definition
   validators use this same contract, without speculative alternative selection.
4. `commit()` validates and then executes selected `onCommit()` effects and
   assignments in recognition order. Effects receive ParseResult as their only
   argument. They never run merely because parsing or output reading succeeds.

Commit returns false for failed syntax or rejected semantic validation.
An accepted result containing explicit [error recovery](recovery.md) also refuses
commit. Its validation reports recovery errors without running semantic validators;
no effects execute on valid siblings or enclosing parsers.
Successful commits are idempotent. Mapping, validation and effect exceptions are
cached and rethrown; a partially executed effect sequence is never retried or
reported as committed. External effects cannot be rolled back automatically.
Recursive value reads, named-result evaluation, validation and commit are errors.

Internally, each selected match owns an effect execution with pending, running,
completed and failed states. Shared child traversal executes effects once;
explicit commit during running effects is rejected. A failed execution retains
the original exception and never transitions back to pending.

Grammar callbacks/validators/mappings/names are snapshotted per recognition.
After CST construction, validation traversal retains children carrying validators,
recoveries or descendant validation. Value-only concrete children do not require
validation traversal. Their separate value, collection and commit dependencies
remain intact, including commit status observable through a selected child result.
Custom execution-record subclasses conservatively retain their validation path.
Discarded alternative callbacks and speculative trivia produce no committed
effects. A semantic rejection does not retry a syntactically selected choice;
use a pure guard when rejection belongs to recognition.

See [semantic values](semantic-values.md), [ParseResult](parse-results.md) and
the [runnable examples](https://github.com/vanderlee/Comprehend/blob/master/docs/examples/execution.php).
