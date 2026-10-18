<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Vanderlee\Comprehend\Extensions\Extraction\Extraction;

$age = Extraction::syntax()->integer(0, 150)
    ->named('age')
    ->mapText(static fn(string $text): int => (int) $text);
$result = $age->parse('42');
if (!$result->ok || $result->output() !== 42 || $result->value() !== 42 || $result->rawText() !== '42') {
    throw new RuntimeException('Build, parse and extract failed');
}
if ($age->parse('151')->success || $age->parse('42!')->success) {
    throw new RuntimeException('Complete-input bounds were not enforced');
}

$declaration = Extraction::syntax()->text('age')->text(':')->then($age);
$spaces = Extraction::syntax()->text(' ')->many();
$result = $declaration->parse('age : 42', spacing: $spaces);
if (!$result->success || $result->ast()->firstNamed('age')->rawText() !== '42') {
    throw new RuntimeException('Spaced declaration extraction failed');
}
if ($declaration->parse('age : 42')->success) {
    throw new RuntimeException('Per-call spacing mutated the grammar');
}

$word = Extraction::syntax()->text('hello')->mapText('strtoupper');
if ($word->parse('hello')->output() !== 'HELLO') {
    throw new RuntimeException('Text mapping failed');
}
$wrapped = $word->prepend('[')->text(']');
if (!$wrapped->parse('[hello]')->ok || !$word->parse('hello')->ok) {
    throw new RuntimeException('Immutable prepending failed');
}

echo "Build, parse, extract and per-call spacing examples passed.\n";
