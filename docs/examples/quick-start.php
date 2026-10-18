<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Numbers\SignPolicy;

$modifier = C::integerValue(-100, 100, allowLeadingZeros: true, sign: SignPolicy::REQUIRED)
    ->optionalValue(default: 0);
$roll = C::collect(
    C::integerValue(1, 100)->thenIgnore('d'),
    C::integerValue(2, 1000)->named('sides'),
    $modifier,
)->map(static fn(array $parts): array => [
    'dice' => $parts[0],
    'sides' => $parts[1],
    'modifier' => $parts[2],
]);

$validRolls = [
    ['2d6+3', ['dice' => 2, 'sides' => 6, 'modifier' => 3]],
    ['1d20', ['dice' => 1, 'sides' => 20, 'modifier' => 0]],
    ['2d6-3', ['dice' => 2, 'sides' => 6, 'modifier' => -3]],
    ['1d2-100', ['dice' => 1, 'sides' => 2, 'modifier' => -100]],
    ['100d1000+100', ['dice' => 100, 'sides' => 1000, 'modifier' => 100]],
    ['2d6+0', ['dice' => 2, 'sides' => 6, 'modifier' => 0]],
    ['2d6-0', ['dice' => 2, 'sides' => 6, 'modifier' => 0]],
    ['2d6+003', ['dice' => 2, 'sides' => 6, 'modifier' => 3]],
];
foreach ($validRolls as [$input, $expected]) {
    $result = $roll->parse($input);
    if (
        !$result->ok
        || $result->output() !== $expected
        || $result->value() !== $expected
        || $result->rawText() !== $input
        || $result->ast()?->firstNamed('sides')?->rawText() !== (string) $expected['sides']
    ) {
        throw new RuntimeException("Dice extraction failed for {$input}");
    }
}

$invalidRolls = [
    '0d6', '101d6', '2d1', '2d1001', '2d6+101', '2d6-101',
    'd6', '2d', '2D6', '2d6+', '2d6-', '2d6 3', '2d6!',
];
foreach ($invalidRolls as $input) {
    $result = $roll->parse($input);
    if ($result->ok || $result->output() !== null || $result->diagnostic === null) {
        throw new RuntimeException("Invalid dice notation accepted: {$input}");
    }
}

$spaces = C::text(' ')->many();
$result = $roll->parse('2d6 +3', spacing: $spaces);
if (
    !$result->success
    || $result->output() !== ['dice' => 2, 'sides' => 6, 'modifier' => 3]
    || $result->rawText() !== '2d6 +3'
    || $result->ast()?->firstNamed('sides')?->rawText() !== '6'
) {
    throw new RuntimeException('Spaced dice extraction failed');
}
if ($roll->parse('2d6 +3')->success || !$roll->parse('2d6+3')->success) {
    throw new RuntimeException('Per-call spacing mutated the grammar');
}
foreach ([' 2d6+3', '2d6+3 ', '2d6 + 3', '2d6 3'] as $input) {
    if ($roll->parse($input, spacing: $spaces)->ok) {
        throw new RuntimeException("Spacing accepted boundary text or split a signed number: {$input}");
    }
}

$word = C::text('hello')->mapText('strtoupper');
if ($word->parse('hello')->output() !== 'HELLO') {
    throw new RuntimeException('Text mapping failed');
}
$wrapped = $word->prepend('[')->text(']');
if (!$wrapped->parse('[hello]')->ok || !$word->parse('hello')->ok) {
    throw new RuntimeException('Immutable prepending failed');
}

echo "Dice notation, typed values, source preservation and per-call spacing examples passed.\n";
