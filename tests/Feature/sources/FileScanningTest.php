<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadUntil;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadWhileRange;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadWhileSet;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteFileSource;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8FileSource;
use Vanderlee\Comprehend\Source\Utf8Source;
use Vanderlee\Comprehend\Source\Span;

uses(TestCase::class);

test('chunked file scans agree with scalar scans across unicode and delimiter boundaries', function (): void {
    foreach ([ByteFileSource::class, Utf8FileSource::class] as $class) {
        foreach ([4, 64, 8192] as $page) {
            foreach ([8190, 8191, 8192, 8193, 16383] as $boundary) {
                foreach ([';', 'aba', "\u{1f642}!", str_repeat('a', 127) . '!'] as $delimiter) {
                    $text = 'x' . str_repeat('a', $boundary - 2) . "\u{e9}" . $delimiter . 'tail';
                    $stream = tmpfile();
                    fwrite($stream, $text);
                    $source = new $class($stream, $page);
                    fclose($stream);
                    $memory = $class === ByteFileSource::class ? new ByteSource($text) : new Utf8Source($text);
                    try {
                        foreach ([[0, null], [0, 0], [1, 8192], [0, 65535], [20000, null]] as [$min, $max]) {
                            $parser = new ReadUntil($delimiter, $min, $max);
                            $fastSession = new ParseSession($source);
                            $scalarSession = new ParseSession($memory, limits: new ParseLimits(maxWork: PHP_INT_MAX));
                            $fast = $parser->recognizeInSession($source, 1, $fastSession);
                            $scalar = $parser->recognizeInSession($memory, 1, $scalarSession);
                            $this->assertSame(
                                [$scalar->accepted, $scalar->length, $scalarSession->workUsed()],
                                [$fast->accepted, $fast->length, $fastSession->workUsed()],
                            );
                            $expected = new ParseResult($scalar);
                            $actual = new ParseResult($fast);
                            $this->assertSame($expected->node?->toArray(), $actual->node?->toArray());
                            $this->assertSame($expected->diagnostic?->toArray(), $actual->diagnostic?->toArray());
                        }
                    } finally {
                        $source->close();
                    }
                }
            }
        }
    }
});

test('chunked sets and ranges preserve bounds eof probes and partial delimiters', function (): void {
    foreach ([ByteFileSource::class, Utf8FileSource::class] as $class) {
        foreach (
            [
                '', '!', str_repeat('a', 16385),
                str_repeat("a\u{e9}\u{20ac}\u{1f642}", 1800) . 'ab', "\0\r\naababa",
            ] as $text
        ) {
            $stream = tmpfile();
            fwrite($stream, 'x' . $text);
            $source = new $class($stream, 4);
            fclose($stream);
            try {
                foreach ([[0, null], [1, 1], [0, 8192], [0, 65536], [20000, null]] as [$min, $max]) {
                    foreach (
                        [new ReadWhileSet("a\u{e9}\u{1f642}", $min, $max),
                            new ReadWhileRange("\0", null, $min, $max),
                            new ReadUntil('aba', $min, $max),
                            new ReadUntil('abc', $min, $max)] as $parser
                    ) {
                        $fastSession = new ParseSession($source);
                        $scalarSession = new ParseSession($source, limits: new ParseLimits(maxWork: PHP_INT_MAX));
                        $fast = $parser->recognizeInSession($source, 1, $fastSession);
                        $scalar = $parser->recognizeInSession($source, 1, $scalarSession);
                        $this->assertSame(
                            [$scalar->accepted, $scalar->length, $scalarSession->workUsed()],
                            [$fast->accepted, $fast->length, $fastSession->workUsed()],
                        );
                        $this->assertSame($scalar->diagnostic?->byteOffset, $fast->diagnostic?->byteOffset);
                    }
                }
            } finally {
                $source->close();
            }
        }
    }
});

test('finite file scan budgets retain exact scalar exhaustion positions', function (): void {
    $stream = tmpfile();
    fwrite($stream, "x\u{e9}\u{1f642}a!");
    $source = new Utf8FileSource($stream, 4);
    fclose($stream);
    try {
        foreach ([1, 2, 3, 4] as $budget) {
            try {
                (new ReadWhileRange("\0", null))->matchPrefix($source, 1, new ParseLimits(maxWork: $budget));
                $this->fail('Expected exhaustion');
            } catch (ParseLimitException $error) {
                $this->assertSame([1 => 1, 2 => 3, 3 => 7, 4 => 8][$budget], $error->byteOffset);
            }
        }
    } finally {
        $source->close();
    }
});

test('large file scans retain bounded temporary subjects and lazy result text', function (): void {
    $stream = tmpfile();
    for ($i = 0; $i < 256; ++$i) {
        fwrite($stream, str_repeat('a', 8192));
    }
    $source = new Utf8FileSource($stream);
    fclose($stream);
    try {
        $parser = new ReadUntil('!');
        $parser->matchPrefix($source, $source->byteLength());
        memory_reset_peak_usage();
        $before = memory_get_usage();
        $result = $parser->matchPrefix($source);
        $this->assertLessThan(1024 * 1024, memory_get_peak_usage() - $before);
        $this->assertTrue($result->success);
        $this->assertSame(2 * 1024 * 1024, $result->end);
        $this->assertSame([], $result->node->children);
        $source->close();
        $this->expectException(LogicException::class);
        $result->rawText();
    } finally {
        $source->close();
    }
});

test('file and memory regex plans keep separate bounded quantifiers', function (): void {
    $stream = tmpfile();
    fwrite($stream, str_repeat('a', 20000));
    $file = new ByteFileSource($stream);
    fclose($stream);
    try {
        $parser = new ReadWhileSet('a', max: 3);
        foreach ([$file, new ByteSource(str_repeat('a', 20000)), $file] as $source) {
            $session = new ParseSession($source);
            $match = $parser->recognizeInSession($source, 0, $session);
            $this->assertSame(3, $match->length);
            $this->assertSame(4, $session->workUsed());
        }
    } finally {
        $file->close();
    }
});

test('file scan callbacks custom sources and case scopes retain scalar behavior', function (): void {
    $stream = tmpfile();
    fwrite($stream, 'aaA!');
    $source = new class ($stream) extends ByteFileSource {
        public int $reads = 0;

        public function readUnit(int $byteOffset): ?Span
        {
            ++$this->reads;

            return parent::readUnit($byteOffset);
        }
    };
    $file = new Utf8FileSource($stream, 4);
    fclose($stream);
    try {
        $this->assertSame(2, (new ReadWhileSet('a'))->matchPrefix($source)->end);
        $this->assertSame(3, $source->reads);
        $this->assertSame(3, (new ReadWhileSet('a'))->setCaseSensitivity(false)->matchPrefix($file)->end);
        $seen = [];
        $parser = new ReadUntil(static function (string $unit) use (&$seen): bool {
            $seen[] = $unit;

            return $unit === '!';
        });
        $this->assertSame(3, $parser->matchPrefix($file)->end);
        $this->assertSame(['a', 'a', 'A', '!'], $seen);
    } finally {
        $source->close();
        $file->close();
    }
});

test('chunked selected results retain cached values explicit effects and snapshot text', function (): void {
    $stream = tmpfile();
    $text = str_repeat("a\u{e9}\u{20ac}\u{1f642}", 1000);
    fwrite($stream, $text . '!');
    $source = new Utf8FileSource($stream, 4);
    fseek($stream, 0);
    fwrite($stream, 'changed');
    fclose($stream);
    $mapped = 0;
    $committed = 0;
    $parser = (new ReadUntil('!'))->mapValue(static function (ParseResult $result) use (&$mapped): int {
        ++$mapped;

        return strlen($result->rawText());
    })->onCommit(static function () use (&$committed): void {
        ++$committed;
    });
    try {
        $result = $parser->matchPrefix($source);
        $this->assertSame([0, 0], [$mapped, $committed]);
        $this->assertSame($text, $result->rawText());
        $this->assertSame(strlen($text), $result->value());
        $this->assertSame(strlen($text), $result->value());
        $this->assertSame([1, 0], [$mapped, $committed]);
        $this->assertTrue($result->commit());
        $this->assertTrue($result->commit());
        $this->assertSame([1, 1], [$mapped, $committed]);
    } finally {
        $source->close();
    }
});

test('file regex execution failures resume scalar scanning without changing work', function (): void {
    $stream = tmpfile();
    $text = str_repeat('a', 18000) . "\xff\0ab";
    fwrite($stream, $text);
    $source = new ByteFileSource($stream);
    fclose($stream);
    $limit = ini_get('pcre.backtrack_limit');
    $jit = ini_get('pcre.jit');
    try {
        ini_set('pcre.jit', '0');
        ini_set('pcre.backtrack_limit', '1');
        $parser = new ReadUntil('ab!');
        $session = new ParseSession($source);
        $match = $parser->recognizeInSession($source, 0, $session);
        $memory = new ByteSource($text);
        $scalar = new ParseSession($memory, limits: new ParseLimits(maxWork: PHP_INT_MAX));
        $reference = $parser->recognizeInSession($memory, 0, $scalar);
        $this->assertSame(
            [$reference->accepted, $reference->length, $scalar->workUsed()],
            [$match->accepted, $match->length, $session->workUsed()],
        );
        $this->assertSame($text, $match->span->rawText());
    } finally {
        ini_set('pcre.backtrack_limit', $limit);
        ini_set('pcre.jit', $jit);
        $source->close();
    }
});
