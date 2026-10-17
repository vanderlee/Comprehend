<?php

declare(strict_types=1);

namespace Tests\Fixtures\PHPStan;

use DateTimeImmutable;
use Vanderlee\Comprehend\Extensions\Extraction;

// Intentionally invalid callbacks: checked by tools/check-value-types.php, never executed.
$number = Extraction::syntax()->integer()->mapText('intval');
$number->map(static fn(DateTimeImmutable $value): string => $value->format('Y'));
Extraction::syntax()->text('x')->mapText(static fn(int $value): int => $value + 1);
$number->mapValue(static fn(string $value): int => strlen($value));
$number->manyValues()->map(static fn(string $value): int => strlen($value));
$number->mapWith($number, static fn(DateTimeImmutable $left, int $right): int => $right);
$number->mapWith($number, static fn(int $left, DateTimeImmutable $right): int => $left);
$number->foldLeft($number, static fn(DateTimeImmutable $left, int $right): int => $right);
$number->foldLeft($number, static fn(int $left, DateTimeImmutable $right): int => $left);
$number->foldLeft($number, static fn(int $left, int $right): string => 'wrong accumulator type');

// Generated factory declarations must not invent custom rules or expose fluent-only factories.
Extraction::syntax()->customVocabularyRule();
Extraction::syntax()->map(static fn(int $value): int => $value);
