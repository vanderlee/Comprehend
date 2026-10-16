<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Extensions\Extraction;
use Vanderlee\Comprehend\Extensions\Expression\Operator as O;
use Vanderlee\Comprehend\Extensions\Expressions;

$syntax = Extraction::syntax()->extend(new Expressions());
$fluent = $syntax->regex('/[0-9]+/A')->mapText('intval')->expressionChoice(
    Extraction::syntax()->text('one')->mapText(static fn(): int => 1),
)->operatorExpression(O::infixLeft('+', 10, static fn(int $a, int $b): int => $a + $b));
if ($fluent->parse('one+2')->value() !== 3) {
    throw new RuntimeException('Fluent expression composition failed');
}
$number = Extraction::syntax()->regex('/[0-9]+/A')->named('number')->mapText('intval');
$rules = new Ruleset();
$primary = $syntax->expressionChoice($number, $syntax->of($rules->rule('expr'))->between('(', ')'));
$rules->define('expr', $syntax->operatorExpression(
    $primary,
    O::infixLeft('+', 10, static fn(int $a, int $b): int => $a + $b),
    O::infixLeft('-', 10, static fn(int $a, int $b): int => $a - $b),
    O::infixLeft('*', 20, static fn(int $a, int $b): int => $a * $b),
    O::prefix('-', 30, static fn(int $a): int => -$a),
    O::infixRight('^', 40, static fn(int $a, int $b): int => $a ** $b),
)->spacing(Extraction::syntax()->text(' ')->many()))->root('expr');
$grammar = $rules->freeze();
foreach (['2 + 3 * 4' => 14, '(2+3)*4' => 20, '10-3-2' => 5, '2^3^2' => 512, '-2^2' => -4] as $text => $value) {
    $result = $grammar->parse($text);
    if (!$result->success || $result->value() !== $value || $result->rawText() !== $text) {
        throw new RuntimeException('Expression values or source round trip failed: ' . $text);
    }
}
foreach (['2+', '()', '(2+3', '2 3', '2^-2', ' 2', '2 '] as $text) {
    if ($grammar->parse($text)->success) {
        throw new RuntimeException('Malformed or ungrouped expression accepted: ' . $text);
    }
}
$comparison = $syntax->operatorExpression(
    $number,
    O::infixNone('<', 10, static fn(int $a, int $b): bool => $a < $b),
);
if ($comparison->parse('1<2')->value() !== true || $comparison->parse('1<2<3')->success) {
    throw new RuntimeException('Non-associative comparison behavior changed');
}
echo "Expression precedence, associativity, grouping and source round trips agree.\n";
