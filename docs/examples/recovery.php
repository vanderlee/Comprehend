<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Vanderlee\Comprehend\Extensions\Binding;
use Vanderlee\Comprehend\Extensions\Recovery as RecoveryExtension;
use Vanderlee\Comprehend\Result\Recovery;
use Vanderlee\Comprehend\Extensions\Recovery\RecoveryContext;
use Vanderlee\Comprehend\Source\Span;

$syntax = Binding::syntax()->extend(new RecoveryExtension());
$effects = 0;
$statement = $syntax->text('ok;')->onCommit(static function () use (&$effects): void {
    ++$effects;
})->recoverUntil(';', consumeBoundary: true);
$result = $statement->some()->parse('ok;bad;wrong;ok;');
if (
    !$result->success || !$result->hasRecovery() || $result->validate()->valid
    || $result->commit() || $effects !== 0
    || array_map(static fn(Recovery $r): string => $r->span->rawText(), $result->recoveries()) !== ['bad;', 'wrong;']
) {
    throw new RuntimeException('Recovery contract failed');
}
foreach ($result->diagnostics() as $diagnostic) {
    echo $diagnostic->message(), PHP_EOL;
}

$group = $syntax->text('ok')->recoverDelimited('(', ')', ignore: $syntax->regex('/"[^"]*"/A'));
$groupResult = $group->then('next')->parse('(bad(nested) ")")next');
if (!$groupResult->success || $groupResult->recoveries()[0]->span->rawText() !== '(bad(nested) ")")') {
    throw new RuntimeException('Delimited recovery failed');
}

$list = $syntax->regex('/[0-9]+/A')->mapText('intval')->recoverItem(',', ']')->between('[', ']');
$listResult = $list->parse('[10,???,30]');
$values = $listResult->value();
if (!$listResult->success || $values[0] !== 10 || !$values[1] instanceof Recovery || $values[2] !== 30) {
    throw new RuntimeException('Item recovery failed');
}

$declaration = $syntax->text('valid')->recoverWith(
    static fn(RecoveryContext $context): ?Span => $context->scanUntil('next'),
    probes: ['next' => $syntax->text('function')],
);
$customResult = $declaration->then('function')->parse('broken function');
if (!$customResult->success || $customResult->recoveries()[0]->span->rawText() !== 'broken ') {
    throw new RuntimeException('Custom recovery failed');
}
