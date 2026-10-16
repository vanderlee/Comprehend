<?php

declare(strict_types=1);

namespace Tests\Src\Builder;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Comprehend;
use Vanderlee\Comprehend\Result\ParseResult;

uses(TestCase::class);

test('build forwards factory arguments and processors', function (): void {
    $definition = new Definition(static fn(string $text): Comprehend => Comprehend::text($text)->setResult('item'));
    $definition->addProcessor('upper', static fn(array $values): string => strtoupper($values['item']));

    $result = $definition->build('word')->parse('word');

    $this->assertTrue($result->success);
    $this->assertSame(['upper' => 'WORD'], $result->results());
});

test('clearing validators allows later builds', function (): void {
    $definition = new Definition(Comprehend::text('a'), static fn(ParseResult $result): bool => false);
    $this->assertFalse($definition->build()->parse('a')->validate()->valid);

    $definition->clearValidators();

    $this->assertTrue($definition->build()->parse('a')->validate()->valid);
});
