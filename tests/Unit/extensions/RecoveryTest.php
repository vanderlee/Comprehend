<?php

declare(strict_types=1);

namespace Tests\Extensions;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Recovery\Recovery;
use Vanderlee\Comprehend\Extensions\Recovery\RecoveryContext;
use Vanderlee\Comprehend\Source\Span;

uses(TestCase::class);

test('recovery factories are discovered before explicit vocabulary installation', function (string $name): void {
    $base = C::syntax();
    $syntax = $base->extend(new Recovery());
    $this->assertContains(strtolower($name), $base->names());
    $this->assertContains(strtolower($name), $syntax->names());
    $this->assertNotNull($syntax->rule($name));
    $this->assertNotNull($syntax->fluent($name));
    $this->assertNotNull($base->rule($name));
    $this->assertNotNull($base->fluent($name));
})->with(['recoverUntil', 'recoverDelimited', 'recoverItem', 'recoverWith']);

test('recovery rules wrap unscoped expressions and retain the installed fluent vocabulary', function (): void {
    $syntax = C::syntax()->extend(new Recovery());
    $plain = C::text('ok');
    $wrapped = $syntax->recoverUntil($plain, ';', consumeBoundary: true);
    $result = $wrapped->freeze()->parse('bad;');
    $this->assertTrue($result->success);
    $this->assertTrue($result->hasRecovery());
    $this->assertFalse($result->commit());
    $this->assertSame('bad;', $result->rawText());
    $this->assertTrue($wrapped->recoverDelimited('(', ')')->parse('(bad;)')->hasRecovery());
    $this->assertFalse($plain->parse('bad;')->success);
    $this->assertTrue($plain->recoverUntil(';')->matchPrefix('bad;')->hasRecovery());
});

test('custom recovery rules and fluents share named probe conversion and byte spans', function (): void {
    $syntax = C::syntax()->extend(new Recovery());
    $strategy = static fn(RecoveryContext $context): ?Span => $context->scanUntil('end', true);
    $probes = ['end' => C::text(';')];
    $rule = $syntax->recoverWith(C::text('ok'), $strategy, probes: $probes);
    $fluent = $syntax->text('ok')->recoverWith($strategy, probes: $probes);
    foreach ([$rule, $fluent] as $parser) {
        $result = $parser->parse('bad;');
        $this->assertTrue($result->success);
        $this->assertSame('bad;', $result->recoveries()[0]->span->rawText());
        $this->assertFalse($result->validate()->valid);
    }
});
