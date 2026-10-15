<?php

declare(strict_types=1);

namespace Tests\Src\Result;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Result\Diagnostic;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;

final class DiagnosticTest extends TestCase
{
    public function testUnicodeColumnsAndCrLfExcerptsUseTheOriginalSource(): void
    {
        $diagnostic = new Diagnostic(new Utf8Source("first\r\n\u{e9}x"), 9, ['digit', 'letter'], [['root']]);

        $this->assertSame(['line' => 2, 'column' => 2], $diagnostic->location());
        $this->assertSame("\u{e9}x", $diagnostic->excerpt());
        $this->assertSame('Expected digit or letter at line 2, column 2 (byte 9)', $diagnostic->message());
    }

    public function testOffsetsOutsideTheSourceAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Diagnostic(new ByteSource('a'), 2, [], []);
    }
}
