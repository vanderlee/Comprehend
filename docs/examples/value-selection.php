<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Vanderlee\Comprehend\Extensions\Binding;

$number = Binding::syntax()->integer()->mapText(static fn(string $text): int => (int) $text);
$numbers = $number->separatedBy(',')->between('[', ']')->spacing(Binding::syntax()->text(' ')->many());
$result = $numbers->parse('[10, 20]');
if (!$result->success || $result->value() !== [10, 20] || $result->rawText() !== '[10, 20]') {
    throw new RuntimeException('Delimited list values or source round trip failed');
}
if ($numbers->parse('[10,20')->success || $numbers->parse('[10,,20]')->success) {
    throw new RuntimeException('Malformed delimited list accepted');
}
if (
    $number->thenIgnore(';')->parse('42;')->value() !== 42
    || Binding::syntax()->text('return:')->ignoreThen($number)->parse('return:42')->value() !== 42
    || Binding::syntax()->text('hello')->between('(', ')')->parse('(hello)')->value() !== 'hello'
) {
    throw new RuntimeException('Selected sequence value changed');
}
echo "Delimiter recognition, selected values and source round trips agree.\n";
