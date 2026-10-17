<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Result\ValidationIssue;

$age = C::integer()->validateSemantic(
    static fn(ParseResult $result): bool|ValidationIssue => (int) $result->rawText() <= 130
        ? true
        : new ValidationIssue('age.range', 'Age exceeds 130', $result->span, ['maximum' => 130]),
);
$result = $age->parse('150');
$report = $result->validate();
if ($report->valid || $result->commit() || $report !== $result->validate()) {
    throw new RuntimeException('Invalid values must retain their report and refuse commit');
}
echo $report->toJson() . PHP_EOL;
