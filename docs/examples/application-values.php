<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/application-values-grammar.php';

use Examples\ApplicationValues\Job;

use function Examples\ApplicationValues\jobParser;

$grammar = jobParser()->freeze();
$input = 'report retry=off [[1, 2], [], [3]]';
$result = $grammar->parse($input);
if (
    !$result->success || !$result->value() instanceof Job
    || $result->value()->name !== 'report' || $result->value()->retries !== false
    || $result->value()->batches !== [[1, 2], [], [3]]
    || $result->rawText() !== $input || $result->value() !== $result->value()
) {
    throw new RuntimeException('Application value tutorial failed');
}
echo "Application value tutorial verified\n";

$valuesOnly = $grammar->parse($input, trees: false);
if (
    !$valuesOnly->success || $valuesOnly->node !== null || $valuesOnly->ast() !== null
    || !$valuesOnly->value() instanceof Job
    || $valuesOnly->value()->batches !== [[1, 2], [], [3]]
    || $valuesOnly->rawText() !== $input
) {
    throw new RuntimeException('Value-only application extraction failed');
}
