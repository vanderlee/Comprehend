<?php

declare(strict_types=1);

namespace Tests\Src\Abnf;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Abnf\Compiler;

uses(TestCase::class);

test('compilation resolves core rules and case insensitive root names', function (): void {
    $grammar = Compiler::compile('token = 2DIGIT', 'TOKEN');

    $this->assertTrue($grammar->parse('42')->success);
    $this->assertFalse($grammar->parse('4x')->success);
    $this->assertFalse($grammar->parse('420')->success);
});

test('undefined root is rejected', function (): void {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Undefined ABNF root: missing');

    Compiler::compile('token = "a"', 'missing');
});
