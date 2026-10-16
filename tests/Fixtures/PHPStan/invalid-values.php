<?php

declare(strict_types=1);

namespace Tests\Fixtures\PHPStan;

use DateTimeImmutable;
use Vanderlee\Comprehend\Comprehend as C;

// Intentionally invalid callbacks: checked by tools/check-value-types.php, never executed.
$number = C::integer()->mapText('intval');
$number->map(static fn(DateTimeImmutable $value): string => $value->format('Y'));
C::text('x')->mapText(static fn(int $value): int => $value + 1);
$number->mapValue(static fn(string $value): int => strlen($value));
$number->manyValues()->map(static fn(string $value): int => strlen($value));
