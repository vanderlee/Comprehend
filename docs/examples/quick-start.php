<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Result\ParseResult;

$age = C::integer(0, 150)
    ->named('age')
    ->mapValue(static fn(ParseResult $r): int => (int) $r->rawText());
$result = $age->parse('42');
if (!$result->ok || $result->output() !== 42 || $result->value() !== 42 || $result->rawText() !== '42') {
    throw new RuntimeException('Build, parse and extract failed');
}
if ($age->parse('151')->success || $age->parse('42!')->success) {
    throw new RuntimeException('Complete-input bounds were not enforced');
}

$declaration = C::text('age')->text(':')->then($age);
$spaces = C::text(' ')->many();
$result = $declaration->parse('age : 42', spacing: $spaces);
if (!$result->success || $result->ast()->firstNamed('age')->rawText() !== '42') {
    throw new RuntimeException('Spaced declaration extraction failed');
}
if ($declaration->parse('age : 42')->success) {
    throw new RuntimeException('Per-call spacing mutated the grammar');
}
echo "Build, parse, extract and per-call spacing examples passed.\n";
