<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Result\ParseResult;

$identifier = C::regex('/[A-Za-z][A-Za-z0-9_]*/A')->label('identifier');
$assignment = C::record([
    'name' => $identifier->thenIgnore('='),
    'count' => C::integerValue(1, 100, strict: true)->label('count from 1 to 100'),
])->spacing(C::text(' ')->many());

foreach (['workers=12', 'workers = 12'] as $input) {
    $result = $assignment->parse($input);
    if (
        !$result->success
        || $result->value() !== ['name' => 'workers', 'count' => 12]
        || $result->rawText() !== $input
    ) {
        throw new RuntimeException('Assignment syntax and extracted values must retain the original text');
    }
}
foreach ([' workers=12', 'workers=12 ', 'workers=?', 'workers=12!', 'workers=0', 'workers=101'] as $input) {
    if ($assignment->parse($input)->success) {
        throw new RuntimeException('Malformed assignments must be rejected: ' . $input);
    }
}

$failed = $assignment->parse('workers = ?');
$json = $failed->diagnostic->toJson(includeLocation: true, includeExcerpt: true);
$details = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
if (
    $details['byteOffset'] !== 10
    || $details['expected'] !== ['count from 1 to 100']
    || $details['location'] !== ['line' => 1, 'column' => 11]
    || $details['excerpt'] !== 'workers = ?'
    || $details['excerptEncoding'] !== 'utf8'
) {
    throw new RuntimeException('Assignment diagnostics must identify the invalid count');
}

$status = C::text('ok')->label('status')->diagnosticRule('status');
$result = $status->parse('?');
$expected = ['byteOffset' => 0, 'expected' => ['status'], 'rulePaths' => [['status']]];
$json = json_encode($result->diagnostics(), JSON_THROW_ON_ERROR);
if (json_decode($json, true, flags: JSON_THROW_ON_ERROR) !== [$expected]) {
    throw new RuntimeException('Diagnostic lists must serialize with the documented compact schema');
}

$valueReads = 0;
$effects = 0;
$checked = $assignment
    ->map(static function (array $value) use (&$valueReads): array {
        ++$valueReads;

        return $value;
    })
    ->validateSemantic(static fn(ParseResult $result): bool => $result->value()['count'] <= 32)
    ->onCommit(static function () use (&$effects): void {
        ++$effects;
    });
$result = $checked->parse('workers=12');
$rejected = $checked->parse('workers=64');
json_encode($result->diagnostics(), JSON_THROW_ON_ERROR);
if (!$result->success || !$rejected->success || $valueReads !== 0 || $effects !== 0) {
    throw new RuntimeException('Recognition and diagnostic serialization must leave semantic work deferred');
}
if (!$result->validate()->valid || !$result->commit() || !$result->commit() || $valueReads !== 1 || $effects !== 1) {
    throw new RuntimeException('Explicit validation and repeated commit must evaluate selected work once');
}
if ($rejected->validate()->valid || $rejected->commit() || $effects !== 1) {
    throw new RuntimeException('Semantic rejection must prevent effects');
}

echo "Consumer grammar, diagnostic JSON, validation and explicit effects verified\n";
