<?php

declare(strict_types=1);

namespace Tests\Src;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Comprehend;
use Vanderlee\Comprehend\Parser\Terminal\Text;

uses(TestCase::class);

test('exported parser cannot mutate the facade', function (): void {
    $grammar = Comprehend::of(new Text('a'));
    $grammar->parser()->guard(static fn(): bool => false);

    $this->assertTrue($grammar->parse('a')->success);
    $this->assertFalse($grammar->parse('b')->success);
});

test('prefix and complete parsing have different consumption policies', function (): void {
    $grammar = Comprehend::text('a');

    $this->assertSame('a', $grammar->matchPrefix('ab')->rawText());
    $this->assertFalse($grammar->parseAll('ab')->success);
    $this->assertTrue($grammar->parse('a')->success);
});
