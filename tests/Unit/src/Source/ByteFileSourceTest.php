<?php

declare(strict_types=1);

namespace Tests\Src\Source;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Fixtures\FailingReadStream;
use Vanderlee\Comprehend\Source\ByteFileSource;
use Vanderlee\Comprehend\Source\SourceMode;

uses(TestCase::class);

test('invalid file offsets and reversed slices fail without poisoning cached reads', function (
    string $operation,
    array $arguments,
): void {
    $stream = tmpfile();
    fwrite($stream, 'abcd');
    $source = new ByteFileSource($stream, 4);
    fclose($stream);
    try {
        expect($source->sliceBytes(0, 4))->toBe('abcd');
        try {
            $source->$operation(...$arguments);
            $this->fail('Expected invalid offset or slice rejection');
        } catch (InvalidArgumentException $error) {
            expect($error->getMessage())->not->toBe('');
        }
        expect($source->sliceBytes(1, 3))->toBe('bc');
    } finally {
        $source->close();
    }
})->with([
    ['sliceBytes', [-1, 1]], ['sliceBytes', [0, 5]], ['sliceBytes', [3, 2]],
    ['readUnit', [-1]], ['readUnit', [5]], ['isUnitBoundary', [-1]], ['isUnitBoundary', [5]],
]);

test('non stream inputs are rejected', function (mixed $input): void {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('readable path or seekable stream');

    new ByteFileSource($input);
})->with([null, 123]);

test('unreadable paths report the source contract exception', function (): void {
    $path = __DIR__ . '/missing-directory/input.bin';
    $warnings = [];
    set_error_handler(static function (int $severity, string $message) use ($path, &$warnings): bool {
        if ($severity !== E_WARNING || !str_contains($message, $path)) {
            return false;
        }
        $warnings[] = $message;

        return true;
    });
    try {
        try {
            new ByteFileSource($path);
            $this->fail('Expected unreadable path rejection');
        } catch (InvalidArgumentException $error) {
            expect($error->getMessage())->toBe('File source requires a readable path or seekable stream');
        }
        expect($warnings)->toHaveCount(1);
    } finally {
        restore_error_handler();
    }
});

test('failed reads restore the borrowed stream position and preserve ownership', function (): void {
    $scheme = 'comprehendreadfailure';
    expect(stream_wrapper_register($scheme, FailingReadStream::class))->toBeTrue();
    $stream = null;
    try {
        $stream = fopen($scheme . '://input', 'rb');
        expect(is_resource($stream))->toBeTrue();
        fseek($stream, 2);
        try {
            new ByteFileSource($stream, 4);
            $this->fail('Expected the source read to fail');
        } catch (RuntimeException $error) {
            expect($error->getMessage())->toBe('Cannot read file source');
        }
        expect(is_resource($stream))->toBeTrue();
        expect(ftell($stream))->toBe(2);
    } finally {
        if (is_resource($stream)) {
            fclose($stream);
        }
        stream_wrapper_unregister($scheme);
    }
});

test('non seekable borrowed streams are rejected without closing them', function (): void {
    $stream = fopen('php://output', 'wb');
    expect(stream_get_meta_data($stream)['seekable'])->toBeFalse();
    // Restoring an unsupported position can itself warn; retain all other diagnostics.
    set_error_handler(static fn(int $severity, string $message): bool => $severity === E_WARNING
        && str_contains($message, 'fseek(): Stream does not support seeking'));
    try {
        try {
            new ByteFileSource($stream);
            $this->fail('Expected non seekable stream rejection');
        } catch (InvalidArgumentException $error) {
            expect($error->getMessage())->toBe('File source requires a seekable stream');
        }
        expect(is_resource($stream))->toBeTrue();
    } finally {
        restore_error_handler();
        fclose($stream);
    }
});

test('page size is validated before opening input', function (): void {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('File page size must be at least 4 bytes');

    new ByteFileSource('unused', 0);
});

test('borrowed stream is preserved while reads use an owned snapshot', function (): void {
    $stream = tmpfile();
    $this->assertIsResource($stream);
    try {
        fwrite($stream, "abcd\xfff");
        fseek($stream, 2);
        $source = new ByteFileSource($stream, 4);
        $this->assertSame(2, ftell($stream));
        fseek($stream, 0);
        fwrite($stream, 'xxxxxx');

        $this->assertSame(SourceMode::BYTES, $source->mode());
        $this->assertSame("d\xfff", $source->sliceBytes(3, 6));
        $this->assertSame("\xff", $source->readUnit(4)->rawText());
        $this->assertNull($source->readUnit(6));
        $source->close();
        $this->assertIsResource($stream);
    } finally {
        fclose($stream);
    }
});

test('closed sources reject even cached reads', function (): void {
    $stream = tmpfile();
    $this->assertIsResource($stream);
    try {
        fwrite($stream, 'abc');
        $source = new ByteFileSource($stream);
    } finally {
        fclose($stream);
    }
    $this->assertSame('a', $source->sliceBytes(0, 1));
    $source->close();
    $source->close();
    $this->assertTrue($source->isClosed());
    $this->expectException(LogicException::class);

    $source->sliceBytes(0, 1);
});

test('checked byte access returns arbitrary octets and eof without poisoning later pages', function (): void {
    $bytes = implode('', array_map(chr(...), range(0, 255)));
    $stream = tmpfile();
    fwrite($stream, $bytes);
    $source = new class ($stream, 4) extends ByteFileSource {
        public function byteValue(int $offset): ?int
        {
            return $this->readByteValue($offset);
        }
    };
    fclose($stream);
    try {
        for ($offset = 255; $offset >= 0; --$offset) {
            $this->assertSame($offset, $source->byteValue($offset));
        }
        $this->assertNull($source->byteValue(256));
        foreach ([-1, 257, PHP_INT_MIN, PHP_INT_MAX] as $offset) {
            try {
                $source->byteValue($offset);
                $this->fail('Expected invalid offset rejection');
            } catch (InvalidArgumentException $error) {
                $this->assertSame('Byte offset is outside the source', $error->getMessage());
            }
        }
        $this->assertSame(255, $source->byteValue(255));
        $this->assertSame("\xfe\xff", $source->sliceBytes(254, 256));
        $source->close();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('File source is closed');
        $source->byteValue(256);
    } finally {
        $source->close();
    }
});
