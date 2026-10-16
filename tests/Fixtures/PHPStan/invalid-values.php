<?php

declare(strict_types=1);

namespace Tests\Fixtures\PHPStan;

use DateTimeImmutable;
use Vanderlee\Comprehend\Extensions\Binding;

// Intentionally invalid callbacks: checked by tools/check-value-types.php, never executed.
$number = Binding::syntax()->integer()->mapText('intval');
$number->map(static fn(DateTimeImmutable $value): string => $value->format('Y'));
Binding::syntax()->text('x')->mapText(static fn(int $value): int => $value + 1);
$number->mapValue(static fn(string $value): int => strlen($value));
$number->manyValues()->map(static fn(string $value): int => strlen($value));
