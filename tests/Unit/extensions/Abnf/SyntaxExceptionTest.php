<?php

declare(strict_types=1);

namespace Tests\Extensions\Abnf;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Extensions\Abnf\SyntaxException;
use Vanderlee\Comprehend\Source\ByteSource;

uses(TestCase::class);

test('exception retains the reason and source location', function (): void {
    $source = new ByteSource("first\r\nsecond");
    $error = new SyntaxException('Expected rule', $source, 8);

    $this->assertSame($source, $error->diagnostic->source);
    $this->assertSame(['Expected rule'], $error->diagnostic->expected);
    $this->assertSame([['ABNF']], $error->diagnostic->rulePaths);
    $this->assertSame('ABNF: Expected rule at line 2, column 2 (byte 8)', $error->getMessage());
});
