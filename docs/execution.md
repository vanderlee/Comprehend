# Execution behavior and limitations

Comprehend interprets parser combinators. Named Rulesets, object constructors,
and helper functions compose the same runtime. BNF-inspired authoring describes
the grammar organization; it does not imply a canonical LR(1) implementation or
general context-free grammar recognition. The runtime has no LR item sets,
parse tables, or shift/reduce engine and does not load BNF/ABNF grammar files.

The examples below use ASCII, where input lengths and offsets agree. Install
development dependencies, then run `php docs/examples/execution.php` from the
repository root. The [example script](examples/execution.php) checks acceptance
and consumed lengths and exits with an error if the examples change. CI runs it.

## Prefix matching and complete input

`match($input, $offset = 0)` attempts recognition at that exact offset. It does
not search forward or require the rest of the input to match. A successful
result's `length` is the consumed length, not the final offset. Callbacks resolve
for the selected successful root before `match()` returns.

```php
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\End;
use Vanderlee\Comprehend\Parser\Terminal\Text;

(new Text('a'))->match('ab');                     // success, length 1
(new Text('a'))->match('za', 1);                  // success, length 1
(new Sequence('a', new End()))->match('ab');       // failure, length 1
(new Sequence('a', new End()))->match('a');        // success, length 1
```

`End` tests the end at the current position. A root ending in `End` requires
complete input from the supplied offset under the current ASCII contract.
Spacing remains governed by the root's spacing policy; `End` does not introduce
a new trailing-whitespace policy. `parseAll()` and `matchPrefix()` are planned
APIs, not current public methods.

## Choice selects locally

`new Choice(...)` inherits the surrounding preference, which defaults to first.
`Choice::first(...)` stops at the first successful alternative.
`Choice::longest(...)` and `Choice::shortest(...)` evaluate all alternatives and
select a successful match with the greatest or least consumed length. Equal
successful lengths retain the earlier alternative. These policies select one
match; they do not retain an ambiguity set.

```php
use Vanderlee\Comprehend\Parser\Structure\Choice;

Choice::first('a', 'ab')->match('abc');            // success, length 1
Choice::longest('a', 'ab')->match('abc');          // success, length 2
Choice::shortest('ab', 'a')->match('abc');         // success, length 1

(new Sequence(Choice::first('a', 'ab'), 'c', new End()))
    ->match('abc');                              // failure, length 1
(new Sequence(Choice::longest('a', 'ab'), 'c', new End()))
    ->match('abc');                              // success, length 3
```

Choice tries alternatives at the same input position while choosing its own
match. Once it succeeds, an enclosing sequence's later failure does not cause it
to try another alternative. Longest choice fixes this example, but does not
provide general backtracking: the longest local match may also prevent a later
item from matching.

```php
(new Sequence(Choice::longest('a', 'ab'), 'b', new End()))
    ->match('ab');                               // failure, length 2
(new Sequence(Choice::first('a', 'ab'), 'b', new End()))
    ->match('ab');                               // success, length 2
```

For shared prefixes, move the common prefix before the decision:

```php
(new Sequence('a', Choice::first('bc', 'c'), new End()))
    ->match('abc');                              // success, length 3
```

When the suffix determines which alternative is valid, another option is to put
that suffix inside each choice branch so the branch can fail before selection.

## Repetition is greedy

`Repeat` takes successive successful child matches until the child fails or the
maximum is reached. It does not give back a successful iteration to satisfy a
later parser. Optional repetition follows the same rule.

```php
use Vanderlee\Comprehend\Parser\Structure\Repeat;

(new Sequence(new Repeat('a'), 'a', new End()))
    ->match('aaa');                              // failure, length 3
(new Sequence('a', new Repeat('a'), new End()))
    ->match('aaa');                              // success, length 3
```

The second grammar reserves the required `a` before consuming additional ones.
For structured lists, repeat the separator and following item together so a
failed next item does not commit a separator alone. The [Repeat reference](reference.md#repeat)
describes bound validation and the `LogicException` for successful zero-length
children. Skipped spacing cannot substitute for child progress.

## Recursion and performance

Ruleset references and `Stub` can express recursion when each recursive path
consumes input before re-entering the same rule. The runnable script demonstrates
`item = 'x' | '(' item ')'` on `((x))`.

Direct left recursion such as `expr = expr '+' term | term`, and indirect cycles
that return to a rule without consuming input, are unsupported. They are not
currently detected and may recurse indefinitely or exhaust resources. The
repetition progress guard does not protect recursive rule calls.

Rewrite that expression grammar as `expr = term *( '+' term )`, then fold the
recognized terms into a left-associative semantic value if required. This rewrite
changes tree shape; check semantic output as well as acceptance. Do not execute
left-recursive examples as a capability test.

There is no memoization or general ambiguity handling. Longest and shortest
choice evaluate every alternative, and repeated grammar work may be expensive.
Runtime and memory costs depend on the grammar, input, and requested outputs;
no linear-time or constant-memory guarantee applies.

## Current Unicode limitation

Terminals and end checks use multibyte lengths alongside byte indexing, while
tokens and results slice with `substr()`. The current implementation therefore
has no consistent multibyte coordinate contract. Declaring `ext-mbstring` makes
the required functions available; it does not make matching Unicode-safe.

On UTF-8 input with UTF-8 mbstring encoding, `Sequence(Text('éa'), End)` can
incorrectly accept `éb`, and a token for `Text('é')` can contain only byte `c3`
instead of the full `c3a9` character. The script prints these known defects
separately from its checked ASCII examples; it does not assert that defects must
remain. Setting an encoding or appending `End` is not a workaround.

Use ASCII input for the documented current matching and offset behavior. Do not
treat this runtime as a reliable validator for arbitrary multibyte text or octet
streams. Phase 1 will unify source access, offsets, extraction, regex coordinates,
and end checks before defining a Unicode and byte-source contract.
