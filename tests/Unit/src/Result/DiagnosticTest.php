<?php

declare(strict_types=1);

namespace Tests\Src\Result;

use InvalidArgumentException;
use JsonException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Result\Diagnostic;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Source;
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
