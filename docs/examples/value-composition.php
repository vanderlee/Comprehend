<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Vanderlee\Comprehend\Extensions\Extraction;

$amount = Extraction::syntax()->integer()->mapText('intval')->map(static fn(int $n): int => $n * 100);
$money = Extraction::syntax()->collect(Extraction::syntax()->valueChoice('USD', 'EUR'), $amount)
    ->map(static fn(array $parts): object => (object) ['currency' => $parts[0], 'cents' => $parts[1]])
    ->spacing(Extraction::syntax()->text(' ')->many());
$result = $money->parse('EUR 12');
if (
    !$result->success || $result->value()->cents !== 1200 || $result->value()->currency !== 'EUR'
    || $result->rawText() !== 'EUR 12' || $result->value() !== $result->value()
) {
    throw new RuntimeException('Value composition failed');
}
echo "Value composition verified\n";
