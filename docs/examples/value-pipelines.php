<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Extraction;

$syntax = Extraction::syntax();
$number = $syntax->integer()->mapText('intval');
$person = $syntax->regex('/[A-Za-z]+/A')->mapWith(
    $number,
    static fn(string $name, int $age): object => (object) ['name' => $name, 'age' => $age],
)->spacing(C::readWhileSet(' '));
$result = $person->parse('Ada 37');
$value = $result->value();
if (!$result->success || $value->name !== 'Ada' || $value->age !== 37 || $result->value() !== $value) {
    throw new RuntimeException('Binary pipelines must combine and cache selected values');
}

$step = $syntax->text('-')->ignoreThen($number);
$subtract = $number->foldLeft($step, static fn(int $left, int $right): int => $left - $right)
    ->spacing(C::readWhileSet(' '));
if ($subtract->parse('10 - 3 - 2')->value() !== 5 || $subtract->parse('10')->value() !== 10) {
    throw new RuntimeException('Left folds must preserve associativity and empty-tail behavior');
}
if ($subtract->parse('10 - ?')->success) {
    throw new RuntimeException('Complete parsing must reject an incomplete step');
}
if ($number->foldLeft($step, static fn(int $a, int $b): int => $a - $b, min: 1)->parse('10')->success) {
    throw new RuntimeException('Fold bounds count accepted steps');
}

echo "Value pipelines, left folds, caching and bounds verified\n";
