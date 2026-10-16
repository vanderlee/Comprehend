<?php

declare(strict_types=1);

namespace Tests\Feature\sources;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteFileSource;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8FileSource;
use Vanderlee\Comprehend\Source\Utf8Source;

abstract class FileSourceTest extends TestCase
{
    protected function stream(string $bytes)
    {
        $stream = tmpfile();
        fwrite($stream, $bytes);

        return $stream;
    }

    public static function malformedUtf8(): array
    {
        return [["\x80"], ["\xc0\x80"], ["\xed\xa0\x80"], ["\xf4\x90\x80\x80"],
            ["\xe2\x82"], [str_repeat('a', 8191) . "\xf0\x9f"], ["\xe0\x80\x80"], ["\xf0\x80\x80\x80"]];
    }
}

uses(FileSourceTest::class);

test('borrowed stream position ownership and immutable snapshot', function () {
    $stream = $this->stream("a\xffbc");
    fseek($stream, 2);
    $source = new ByteFileSource($stream, 4);
    $this->assertSame(2, ftell($stream));
    fseek($stream, 0);
    fwrite($stream, 'changed');
    fclose($stream);
    $this->assertSame("a\xffbc", $source->sliceBytes(0, 4));
    $this->assertSame("\xff", $source->readUnit(1)->rawText());
    $this->assertNull($source->readUnit(4));
    $source->close();
    $source->close();
    $this->assertTrue($source->isClosed());
    $this->expectException(LogicException::class);
    $source->sliceBytes(0, 0);
});

test('path can change while retained results use snapshot', function () {
    $path = tempnam(sys_get_temp_dir(), 'comprehend-test-');
    try {
        file_put_contents($path, 'abc');
        $source = new ByteFileSource($path);
        $result = (new Text('abc'))->name('word')->parseAll($source);
        file_put_contents($path, 'xyz');
        unset($source);
        $this->assertSame('abc', $result->rawText());
        $this->assertSame('abc', $result->ast()->rawText());
        $result->span->source->close();
        $this->expectException(LogicException::class);
        $result->node->rawText();
    } finally {
        unlink($path);
    }
});

test('file and memory recognition and random slices agree', function () {
    foreach (
        [
            ['abcxyz', ByteFileSource::class, ByteSource::class],
            ['é🙂abc', Utf8FileSource::class, Utf8Source::class],
        ] as [$bytes, $fileClass, $memoryClass]
    ) {
        $stream = $this->stream($bytes);
        $file = new $fileClass($stream, 4);
        fclose($stream);
        $memory = new $memoryClass($bytes);
        for ($start = strlen($bytes); $start >= 0; --$start) {
            $this->assertSame(
                $memory->sliceBytes($start, strlen($bytes)),
                $file->sliceBytes($start, strlen($bytes)),
            );
            $this->assertSame($memory->isUnitBoundary($start), $file->isUnitBoundary($start));
        }
        $parser = new Text($bytes);
        $this->assertSame($parser->parseAll($memory)->success, $parser->parseAll($file)->success);
        $this->assertSame($bytes, $parser->parseAll($file)->rawText());
    }
});

test('utf8 validation and reads cross page and validation boundaries', function () {
    $bytes = str_repeat('a', 8191) . '🙂é';
    $stream = $this->stream($bytes);
    $source = new Utf8FileSource($stream, 4);
    fclose($stream);
    $this->assertSame(8193, $source->codePointLength());
    $this->assertSame('🙂', $source->readCodePoint(8191)->rawText());
    $this->assertSame(8195, $source->byteOffsetForCodePointPosition(8192));
    $this->assertSame(8192, $source->codePointPositionForByteOffset(8195));
    $this->assertFalse($source->isCodePointBoundary(8192));
    $result = (new Sequence(new Text('🙂'), new Text('é')))->parseAll($source, 8191);
    $this->assertTrue($result->success);
    $this->assertSame('🙂é', $result->rawText());
});

test('malformed encoding rejected and borrowed handle preserved', function (string $bytes) {
    $stream = $this->stream($bytes);
    fseek($stream, 1);
    try {
        new Utf8FileSource($stream, 4);
        $this->fail('Expected encoding rejection');
    } catch (InvalidArgumentException $error) {
        $this->assertStringContainsString('valid UTF-8', $error->getMessage());
        $this->assertSame(1, ftell($stream));
    } finally {
        fclose($stream);
    }
})->with(FileSourceTest::malformedUtf8());

test('empty file and failure positions remain defined', function () {
    $stream = $this->stream('');
    $source = new Utf8FileSource($stream);
    fclose($stream);
    $this->assertSame(0, $source->byteLength());
    $this->assertSame(0, $source->codePointLength());
    $this->assertSame('', $source->span(0, 0)->rawText());
    $result = (new Text('a'))->parseAll($source);
    $this->assertSame(0, $result->farthestFailure);
    $this->assertSame(['line' => 1, 'column' => 1], $result->diagnostic->location());
});

test('diagnostic cr lf index crosses chunk boundary', function () {
    $stream = $this->stream(str_repeat('x', 8191) . "\r\né");
    $source = new Utf8FileSource($stream);
    fclose($stream);
    $result = (new Text('!'))->parseAll($source, 8193);
    $this->assertSame(['line' => 2, 'column' => 1], $result->diagnostic->location());
    $this->assertSame('é', $result->diagnostic->excerpt());
    $eof = (new Text('!'))->parseAll($source, 8195);
    $this->assertSame(['line' => 2, 'column' => 2], $eof->diagnostic->location());
});
