<?php

declare(strict_types=1);

namespace Tests\Src\Result;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Result\Diagnostic;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;

uses(TestCase::class);

test('unicode columns and cr lf excerpts use the original source', function (): void {
    $diagnostic = new Diagnostic(new Utf8Source("first\r\n\u{e9}x"), 9, ['digit', 'letter'], [['root']]);

    $this->assertSame(['line' => 2, 'column' => 2], $diagnostic->location());
    $this->assertSame("\u{e9}x", $diagnostic->excerpt());
    $this->assertSame('Expected digit or letter at line 2, column 2 (byte 9)', $diagnostic->message());
});

test('offsets outside the source are rejected', function (): void {
    $this->expectException(InvalidArgumentException::class);

    new Diagnostic(new ByteSource('a'), 2, [], []);
});

test('diagnostic byte positions may be inside code points or at EOF', function (): void {
    $source = new Utf8Source("\u{e9}");
    $inside = new Diagnostic($source, 1, [], []);
    $end = new Diagnostic($source, 2, [], []);

    $this->assertSame(1, $inside->byteOffset);
    $this->assertSame(2, $end->byteOffset);
});

test('negative diagnostic offsets remain invalid', function (): void {
    $this->expectException(InvalidArgumentException::class);

    new Diagnostic(new ByteSource('a'), -1, [], []);
});
