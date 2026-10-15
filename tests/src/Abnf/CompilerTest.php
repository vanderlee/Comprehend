<?php

declare(strict_types=1);

namespace Tests\Src\Abnf;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Abnf\Compiler;

final class CompilerTest extends TestCase
{
    public function testCompilationResolvesCoreRulesAndCaseInsensitiveRootNames(): void
    {
        $grammar = Compiler::compile('token = 2DIGIT', 'TOKEN');

        $this->assertTrue($grammar->parse('42')->success);
        $this->assertFalse($grammar->parse('4x')->success);
        $this->assertFalse($grammar->parse('420')->success);
    }

    public function testUndefinedRootIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Undefined ABNF root: missing');

        Compiler::compile('token = "a"', 'missing');
    }
}
