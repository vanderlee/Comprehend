<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Vanderlee\Comprehend\Comprehend as C;

$identifier = C::regex('/[A-Za-z][A-Za-z0-9_]*/A')
    ->label('identifier')->diagnosticRule('variable')->named('name');
$missing = $identifier->parse('?');
if (
    $missing->success
    || $missing->diagnostic?->expected !== ['identifier']
    || $missing->diagnostic->rulePaths !== [['variable']]
) {
    throw new RuntimeException('Entry failures must use the expectation label and the original rule name');
}
$accepted = $identifier->parse('answer');
if (!$accepted->success || $accepted->value() !== 'answer' || $accepted->node?->name !== 'name') {
    throw new RuntimeException('Labels must preserve values and tree names');
}

$answers = C::first(C::text('yes')->label('yes answer'), C::text('no')->label('no answer'));
if ($answers->parse('?')->diagnostic?->expected !== ['yes answer', 'no answer']) {
    throw new RuntimeException('Alternative expectation labels must accumulate');
}
$partial = C::text('hello')->label('greeting')->parse('hel?');
if ($partial->diagnostic?->byteOffset !== 3 || $partial->diagnostic->expected !== ['"hello"']) {
    throw new RuntimeException('Partial failures must retain their deeper expectations');
}

echo "Expectation labels, rule paths and deeper failures verified\n";
