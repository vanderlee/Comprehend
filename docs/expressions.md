# Expression builder extension

`Extensions\Expressions` installs an optional precedence-table builder. It composes
the existing parser graph and recognition runtime, with lazy cached reductions and
explicit commit effects. It does not enable left recursion or introduce a separate
Pratt/LR recognition engine.

`Operator` and `OperatorKind` live in `Extensions\Expression`; internal parser
implementations live in `Extensions\Expression\Parser`.

```php
use Vanderlee\Comprehend\Extensions\Extraction;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Expression\Operator as O;
use Vanderlee\Comprehend\Extensions\Expressions;

$syntax = Extraction::syntax()->extend(new Expressions());
$number = Extraction::syntax()->regex('/[0-9]+/A')->mapText('intval');
$expression = $syntax->operatorExpression(
    $number,
    O::infixLeft('+', 10, static fn(int $a, int $b): int => $a + $b),
    O::infixLeft('*', 20, static fn(int $a, int $b): int => $a * $b),
    O::prefix('-', 30, static fn(int $a): int => -$a),
    O::infixRight('^', 40, static fn(int $a, int $b): int => $a ** $b),
)->spacing(Extraction::syntax()->text(' ')->many());

$result = $expression->parse('2 + 3 * 4');
$result->value();   // 14, lazy and cached
$result->rawText(); // '2 + 3 * 4'
```

The original Syntax vocabulary and global Comprehend factories remain unchanged.
Both factories also have fluent forms. `primary->operatorExpression(...operators)`
uses the current expression as its primary, rather than appending another parser.
`primary->expressionChoice(...alternatives)` includes the current expression as
the first alternative and preserves the selected value lazily. Start the primary
through the extended vocabulary to keep these operations available:

```php
$expression = $syntax->regex('/[0-9]+/A')->mapText('intval')->operatorExpression(
    O::infixLeft('+', 10, static fn(int $a, int $b): int => $a + $b),
);
$expression->parse('2+3')->value(); // 5
```

These fluent forms replace the previous chained rule fallback, which appended a
separately constructed expression or choice to the current expression.

No operators are supplied implicitly. Operands and operator syntax accept facades,
Parser objects or literal strings; operator definitions are explicit `Operator`
objects. Definitions detach caller-owned parser builders when constructed.

## Precedence and associativity

Higher integer precedence binds tighter. Integers may be negative, and declaration
order need not follow precedence order. Every operator at a given precedence must
have the same kind; mixing kinds raises InvalidArgumentException during authoring.
Give prefix, postfix, and different infix associativities distinct precedences.

| Definition | Behavior |
| --- | --- |
| `O::infixLeft(syntax, precedence, reducer)` | `a-b-c` becomes `(a-b)-c` |
| `O::infixRight(syntax, precedence, reducer)` | `a^b^c` becomes `a^(b^c)` |
| `O::infixNone(syntax, precedence, reducer)` | At most one operator at that level; `a<b<c` fails complete parsing |
| `O::prefix(syntax, precedence, reducer)` | Repeated prefix operators apply right to left |
| `O::postfix(syntax, precedence, reducer)` | Repeated postfix operators apply left to right |

Unary reducers receive one operand value. Binary reducers receive left and right
operand values. They may return scalars, objects, arrays or null, so a reducer can
build domain AST objects instead of calculating numbers. Reducers should be pure;
application effects belong in `onCommit()`.

Operator alternatives at one level retain declaration order and the session's
choice policy. Put overlapping longer spellings before shorter ones, or apply
`prefer(ChoicePolicy::LONGEST)` explicitly. A later missing operand does not retry
an already selected operator. An empty operator spelling is rejected by Text;
nullable operator parsers and consuming-operand violations are rejected by graph
analysis, with runtime progress checks for custom parsers.

This is a precedence-table grammar: each level takes operands from tighter levels.
With prefix `-` below `^`, `-2^2` means `-(2^2)`, but a negative exponent must be
grouped as `2^(-2)` rather than `2^-2`. Choose reducers that support the desired
numeric types; overflow, division and arithmetic exceptions follow normal PHP rules.

## Parentheses and alternative values

Use explicit recursive Ruleset references for grouping:

```php
use Vanderlee\Comprehend\Extensions\Extraction;
use Vanderlee\Comprehend\Builder\Ruleset;

$rules = new Ruleset();
$primary = $syntax->expressionChoice(
    $number,
    $syntax->of($rules->rule('expr'))->between('(', ')'),
);
$rules->define('expr', $syntax->operatorExpression(
    $primary,
    O::infixLeft('+', 10, static fn(int $a, int $b): int => $a + $b),
    O::infixLeft('*', 20, static fn(int $a, int $b): int => $a * $b),
)->spacing(Extraction::syntax()->text(' ')->many()))->root('expr');

$rules->freeze()->parse('(2+3)*4')->value(); // 20
```

`expressionChoice(...alternatives)` forwards the selected alternative's value
lazily, including values from grouped expressions. Ordinary `choice()`/`or()`
retain their existing default raw-text projection. With no operator definitions,
`operatorExpression(primary)` preserves the primary parser's behavior and value.

## Sources, spacing and lifecycle

No whitespace is inserted into operator syntax. Existing intervening spacing and
lexeme policies apply. The builder does not trim document boundaries; recognize
leading and trailing trivia explicitly when wanted. An incomplete extension such
as `1 + bad` may yield the prefix `1`, with its abandoned spacing and operator
discarded. Complete-input parsing rejects the remaining text. Prefix parsing of a
non-associative chain likewise returns the accepted first comparison.

All syntax, including operators, parentheses and accepted trivia, remains in the
CST with byte-based spans. Naming and tags retain their usual AST behavior. Domain
values do not rewrite the source tree into an operator-shaped AST automatically;
construct that representation in reducers when needed. Byte and UTF-8 sources,
file snapshots, graph freezing and recognition limits use their existing contracts.

Parsing, AST inspection and raw-text reads do not run reducers. `value()` evaluates
and caches the selected reductions or thrown error for that recognition. A later
`mapValue()` or `mapText()` on the expression replaces the default reduction.
Operator parser mappings are not evaluated to select the reducer; validators may
still explicitly read them. All accepted operands/operators retain their named
collections, semantic validation and ordered explicit effects. Discarded attempts
contribute no selected validation or committed effects. Reduction callbacks are
ordinary PHP work and are not preempted by recognition budgets.

Internally, the extension specializes Sequence, Choice, Repeat and SeparatedList
only to project lazy values. The shared list runtime handles chain spacing and
rollback; the core list's default item-value projection remains unchanged.

Run `php docs/examples/expressions.php` for checked arithmetic and grouping.
