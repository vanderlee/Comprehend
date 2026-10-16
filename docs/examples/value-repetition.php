<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Vanderlee\Comprehend\Comprehend as C;

// A record with an optional label and a list of single-digit application values.
$label = C::regex('/[A-Za-z]+/A')->thenIgnore(':')->optionalValue(default: 'untitled');
$digits = C::range('0', '9')->mapText('intval')->manyValues()->between('[', ']');
$record = C::collect($label, $digits)
    ->map(static fn(array $parts): object => (object) ['label' => $parts[0], 'digits' => $parts[1]])
    ->spacing(C::text(' ')->many());

foreach (['scores: [1 2 3]' => ['scores', [1, 2, 3]], '[]' => ['untitled', []]] as $input => $expected) {
    $result = $record->parse($input);
    if (
        !$result->success || $result->rawText() !== $input
        || [$result->value()->label, $result->value()->digits] !== $expected
        || $result->value() !== $result->value()
    ) {
        throw new RuntimeException('Repeated and optional values failed');
    }
}
if ($record->parse('scores [1]')->success || $record->parse('[1 x]')->success) {
    throw new RuntimeException('Malformed fields must not be silently accepted');
}
echo "Repeated and optional application values verified\n";
