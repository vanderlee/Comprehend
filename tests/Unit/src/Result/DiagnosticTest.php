<?php

declare(strict_types=1);

namespace Tests\Src\Result;

use InvalidArgumentException;
use JsonException;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Result\Diagnostic;
use Vanderlee\Comprehend\Source\ByteFileSource;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\SourceMode;
use Vanderlee\Comprehend\Source\Utf8FileSource;
use Vanderlee\Comprehend\Source\Utf8Source;

uses(TestCase::class);

test('locations and excerpts agree with newline boundaries at every small source byte offset', function (
    string $text,
): void {
    preg_match_all('/\r\n|\r|\n/', $text, $newlines, PREG_OFFSET_CAPTURE);
    $lines = preg_split('/\r\n|\r|\n/', $text);
    $sources = [new ByteSource($text)];
    if (mb_check_encoding($text, 'UTF-8')) {
        $sources[] = new Utf8Source($text);
    }
    foreach ($sources as $source) {
        for ($offset = 0; $offset <= strlen($text); ++$offset) {
            $line = 1;
            $start = 0;
            foreach ($newlines[0] as [$newline, $position]) {
                if ($position + strlen($newline) <= $offset) {
                    ++$line;
                    $start = $position + strlen($newline);
                }
            }
            $prefix = substr($text, $start, $offset - $start);
            $column = 1 + ($source->mode() === SourceMode::UTF8 ? mb_strlen($prefix, 'UTF-8') : strlen($prefix));
            foreach ([false, true] as $excerptFirst) {
                $diagnostic = new Diagnostic($source, $offset, ['item'], [['root']]);
                if ($excerptFirst) {
                    $this->assertSame($lines[$line - 1], $diagnostic->excerpt());
                }
                $this->assertSame(['line' => $line, 'column' => $column], $diagnostic->location());
                $this->assertSame($lines[$line - 1], $diagnostic->excerpt());
                $this->assertSame(
                    "Expected item at line $line, column $column (byte $offset)",
                    $diagnostic->message(),
                );
                $this->assertSame(['line' => $line, 'column' => $column], $diagnostic->location());
            }
        }
    }
})->with([
    'empty' => [''], 'CR' => ["\r"], 'LF' => ["\n"], 'CRLF' => ["\r\n"],
    'adjacent newlines' => ["a\r\r\n\n\rb\r\n"],
    'UTF8 interior bytes and NUL' => ["\u{e9}\r\n\u{1f642}\r\t\0x\n"],
    'binary interior bytes' => ["\xff\r\n\xc3\0x\r\n"],
]);

test('CRLF at a diagnostic scan boundary retains positions within the terminator', function (bool $file): void {
    $text = str_repeat('x', 8191) . "\r\n\u{e9}\rZ\n";
    $stream = tmpfile();
    fwrite($stream, $text);
    $source = $file ? new Utf8FileSource($stream, 4) : new Utf8Source($text);
    fclose($stream);
    try {
        $cases = [
            8191 => [1, 8192, str_repeat('x', 8191)],
            8192 => [1, 8193, str_repeat('x', 8191)],
            8193 => [2, 1, "\u{e9}"],
            8194 => [2, 2, "\u{e9}"],
            8195 => [2, 2, "\u{e9}"],
            8196 => [3, 1, 'Z'],
            8198 => [4, 1, ''],
        ];
        foreach ($cases as $offset => [$line, $column, $excerpt]) {
            $diagnostic = new Diagnostic($source, $offset, [], []);
            $this->assertSame($excerpt, $diagnostic->excerpt());
            $this->assertSame(['line' => $line, 'column' => $column], $diagnostic->location());
        }
    } finally {
        if ($source instanceof Utf8FileSource) {
            $source->close();
        }
    }
})->with([false, true]);

test('location reads only its prefix and excerpts stop within one bounded read window', function (): void {
    $text = "a\r\n\u{e9}x\n" . str_repeat("later\n", 10000);
    $source = $this->createMock(Source::class);
    $source->method('byteLength')->willReturn(strlen($text));
    $source->method('mode')->willReturn(SourceMode::UTF8);
    $reads = [];
    $source->method('sliceBytes')->willReturnCallback(
        static function (int $start, int $end) use ($text, &$reads): string {
            $reads[] = [$start, $end];

            return substr($text, $start, $end - $start);
        },
    );
    $diagnostic = new Diagnostic($source, 5, [], []);
    $this->assertSame(['line' => 2, 'column' => 2], $diagnostic->location());
    foreach ($reads as [$start, $end]) {
        $this->assertLessThanOrEqual(5, $end);
    }
    $readLength = static fn(array $read): int => $read[1] - $read[0];
    $bytes = array_sum(array_map($readLength, $reads));
    $diagnostic->location();
    $diagnostic->message();
    $this->assertSame($bytes, array_sum(array_map($readLength, $reads)));
    $this->assertSame("\u{e9}x", $diagnostic->excerpt());
    foreach ($reads as [$start, $end]) {
        $this->assertLessThanOrEqual(5 + 8192, $end);
    }
    $bytes = array_sum(array_map($readLength, $reads));
    $this->assertSame("\u{e9}x", $diagnostic->excerpt());
    $this->assertSame($bytes + 3, array_sum(array_map($readLength, $reads)));
});

test('a trailing prefix CR reads one lookahead byte and no further suffix', function (string $next): void {
    $text = "a\r" . $next . str_repeat('suffix', 10000);
    $source = $this->createMock(Source::class);
    $source->method('byteLength')->willReturn(strlen($text));
    $source->method('mode')->willReturn(SourceMode::BYTES);
    $source->method('sliceBytes')->willReturnCallback(function (int $start, int $end) use ($text): string {
        $this->assertLessThanOrEqual(3, $end);

        return substr($text, $start, $end - $start);
    });
    $diagnostic = new Diagnostic($source, 2, [], []);
    $this->assertSame(
        $next === "\n" ? ['line' => 1, 'column' => 3] : ['line' => 2, 'column' => 1],
        $diagnostic->location(),
    );
})->with(["\n", 'x', "\r"]);

test('cached diagnostic source details still reject closed file snapshots', function (bool $utf8, string $warm): void {
    $stream = tmpfile();
    fwrite($stream, "first\r\nx");
    $source = $utf8 ? new Utf8FileSource($stream) : new ByteFileSource($stream);
    fclose($stream);
    $diagnostic = new Diagnostic($source, 7, ['item'], [['root']]);
    if ($warm !== 'none') {
        $diagnostic->$warm();
    }
    $source->close();
    $reads = [
        static fn() => $diagnostic->location(),
        static fn() => $diagnostic->message(),
        static fn() => $diagnostic->excerpt(),
        static fn() => $diagnostic->toJson(includeLocation: true),
        static fn() => $diagnostic->toJson(includeExcerpt: true),
    ];
    foreach ($reads as $read) {
        expect($read)->toThrow(LogicException::class, 'File source is closed');
    }
    $this->assertSame(
        ['byteOffset' => 7, 'expected' => ['item'], 'rulePaths' => [['root']]],
        $diagnostic->toArray(),
    );
})->with([false, true])->with(['none', 'location', 'excerpt']);

test('failed location scans can retry without retaining a partial line position', function (): void {
    $text = str_repeat("x\n", 5000) . 'last';
    $source = $this->createMock(Source::class);
    $source->method('byteLength')->willReturn(strlen($text));
    $source->method('mode')->willReturn(SourceMode::BYTES);
    $fail = true;
    $source->method('sliceBytes')->willReturnCallback(
        static function (int $start, int $end) use ($text, &$fail): string {
            if ($start === 8192 && $fail) {
                $fail = false;
                throw new RuntimeException('Transient read failure');
            }

            return substr($text, $start, $end - $start);
        },
    );
    $diagnostic = new Diagnostic($source, strlen($text), [], []);
    expect(static fn() => $diagnostic->location())->toThrow(RuntimeException::class, 'Transient read failure');
    $this->assertSame(['line' => 5001, 'column' => 5], $diagnostic->location());
    $this->assertSame('last', $diagnostic->excerpt());
});

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

test('default serialization preserves failure details without reading source bytes', function (): void {
    $source = $this->createMock(Source::class);
    $source->method('byteLength')->willReturn(10);
    $source->expects($this->never())->method('sliceBytes');
    $source->expects($this->never())->method('readUnit');
    $diagnostic = new Diagnostic($source, 3, ['digit', 'letter'], [['root', 'number'], ['root', 'word']]);
    $expected = [
        'byteOffset' => 3,
        'expected' => ['digit', 'letter'],
        'rulePaths' => [['root', 'number'], ['root', 'word']],
    ];

    $this->assertSame($expected, $diagnostic->toArray());
    $this->assertSame($expected, json_decode($diagnostic->toJson(), true, flags: JSON_THROW_ON_ERROR));
    $this->assertSame($diagnostic->toJson(), json_encode($diagnostic, JSON_THROW_ON_ERROR));
    $this->assertSame([$expected], json_decode(json_encode([$diagnostic], JSON_THROW_ON_ERROR), true));
});

test('source details can be selected independently and retain unicode locations', function (): void {
    $diagnostic = new Diagnostic(new Utf8Source("first\r\n\u{e9}x"), 9, ['digit'], [['root']]);
    $expected = ['byteOffset' => 9, 'expected' => ['digit'], 'rulePaths' => [['root']]];
    $location = ['location' => ['line' => 2, 'column' => 2]];
    $excerpt = ['excerpt' => "\u{e9}x", 'excerptEncoding' => 'utf8'];

    $this->assertSame($expected + $location, $diagnostic->toArray(includeLocation: true));
    $this->assertSame($expected + $excerpt, $diagnostic->toArray(includeExcerpt: true));
    $this->assertSame(
        $expected + $location + $excerpt,
        json_decode($diagnostic->toJson(includeLocation: true, includeExcerpt: true), true, flags: JSON_THROW_ON_ERROR),
    );
    $this->assertSame($expected, $diagnostic->toArray());
});

test('binary source excerpts round trip without replacing invalid bytes', function (): void {
    $raw = "\xffx\0";
    $diagnostic = new Diagnostic(new ByteSource($raw), 1, ['digit'], [['root']]);
    $json = $diagnostic->toJson(includeLocation: true, includeExcerpt: true);
    $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

    $this->assertSame(['line' => 1, 'column' => 2], $data['location']);
    $this->assertSame('base64', $data['excerptEncoding']);
    $this->assertSame($raw, base64_decode($data['excerpt'], true));
    $this->assertSame($raw, $diagnostic->excerpt());
});

test('valid utf8 byte excerpts and empty eof excerpts use utf8 encoding', function (): void {
    foreach (["\u{e9}" => 1, '' => 0] as $raw => $offset) {
        $diagnostic = new Diagnostic(new ByteSource($raw), $offset, [], []);

        $this->assertSame([
            'byteOffset' => $offset,
            'expected' => [],
            'rulePaths' => [],
            'excerpt' => $raw,
            'excerptEncoding' => 'utf8',
        ], $diagnostic->toArray(includeExcerpt: true));
    }
});

test('invalid utf8 diagnostic metadata throws rather than silently replacing labels', function (): void {
    $diagnostic = new Diagnostic(new ByteSource(''), 0, ["\xff"], []);
    $this->assertSame(["\xff"], $diagnostic->toArray()['expected']);
    $this->expectException(JsonException::class);

    $diagnostic->toJson();
});

test('serializing observed failures leaves selected values validation and effects deferred', function (): void {
    $values = 0;
    $validations = 0;
    $effects = 0;
    $parser = C::first('abcd', 'a')
        ->map(static function (string $text) use (&$values): string {
            ++$values;

            return strtoupper($text);
        })
        ->validateSemantic(static function () use (&$validations): bool {
            ++$validations;

            return true;
        })
        ->onCommit(static function () use (&$effects): void {
            ++$effects;
        });
    $result = $parser->matchPrefix('abcx');

    $this->assertTrue($result->success);
    $this->assertSame([], $result->diagnostics());
    $json = $result->diagnostic->toJson(includeLocation: true, includeExcerpt: true);
    $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    $this->assertSame(3, $data['byteOffset']);
    $this->assertSame(['"abcd"'], $data['expected']);
    $this->assertSame('abcx', $data['excerpt']);
    $this->assertSame([0, 0, 0], [$values, $validations, $effects]);
    $this->assertFalse($result->isCommitted());

    $this->assertSame('A', $result->value());
    $this->assertTrue($result->validate()->valid);
    $this->assertTrue($result->commit());
    $this->assertSame([1, 1, 1], [$values, $validations, $effects]);
});
