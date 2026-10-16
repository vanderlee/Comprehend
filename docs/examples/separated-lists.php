<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Vanderlee\Comprehend\Comprehend as C;

$numbers = C::integer()->mapText(static fn(string $text): int => (int) $text)
    ->named('number')->separatedBy(',', min: 1, allowTrailing: true)
    ->spacing(C::text(' ')->many());
$result = $numbers->parse('10, 20,');
if (!$result->success || $result->value() !== [10, 20] || $result->rawText() !== '10, 20,') {
    throw new RuntimeException('Separated list values or source round trip failed');
}
if ($numbers->parse('10,,20')->success || $numbers->parse(',')->success) {
    throw new RuntimeException('Malformed separated list accepted');
}
$prefix = $numbers->matchPrefix('10, x');
if (!$prefix->success || $prefix->rawText() !== '10,' || $prefix->value() !== [10]) {
    throw new RuntimeException('Trailing separator prefix behavior changed');
}
echo "Separated list values, spacing, malformed input and source round trips agree.\n";
