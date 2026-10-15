<?php

declare(strict_types=1);

namespace Tests\Src\Abnf;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Abnf\SyntaxException;
use Vanderlee\Comprehend\Source\ByteSource;

final class SyntaxExceptionTest extends TestCase
{
    public function testExceptionRetainsTheReasonAndSourceLocation(): void
    {
        $source = new ByteSource("first\r\nsecond");
        $error = new SyntaxException('Expected rule', $source, 8);

        $this->assertSame($source, $error->diagnostic->source);
        $this->assertSame(['Expected rule'], $error->diagnostic->expected);
        $this->assertSame([['ABNF']], $error->diagnostic->rulePaths);
        $this->assertSame('ABNF: Expected rule at line 2, column 2 (byte 8)', $error->getMessage());
    }
}
