<?php

declare(strict_types=1);

namespace Tests\Src\Directive;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Directive\Trivia;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;

uses(TestCase::class);

test('trivia retains concrete text and omits named ast children', function (): void {
    $parser = new Trivia((new Text(' '))->name('space'));
    $result = $parser->parseAll(new ByteSource(' '));

    $this->assertTrue($result->success);
    $this->assertTrue($result->node->isTrivia);
    $this->assertSame(' ', $result->rawText());
    $this->assertSame([], $result->ast()->children);
    $this->assertFalse($parser->parseAll(new ByteSource('x'))->success);
});
