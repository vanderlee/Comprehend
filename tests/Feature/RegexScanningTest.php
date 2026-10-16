<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Parser\Terminal\ReadUntil;
use Vanderlee\Comprehend\Parser\Terminal\ReadWhileRange;
use Vanderlee\Comprehend\Parser\Terminal\ReadWhileSet;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;

uses(TestCase::class);

test('regex sets agree with scalar scanning across modes bounds case policy and escapes', function (): void {
    $sets = [" \t\r\n", 'abc012', 'é🙂α', "[]^-\\~\0", 'éÉİi', "\xff\x80\0",
        implode('', array_map('chr', range(0, 255)))];
    foreach ($sets as $setIndex => $set) {
        foreach ([false, true] as $utf8) {
            if ($utf8 && !mb_check_encoding($set, 'UTF-8')) {
                continue;
            }
            $units = $utf8 ? mb_str_split($set . '!x', 1, 'UTF-8') : str_split($set . '!x');
            foreach ([[0, null], [0, 0], [1, 1], [2, 5], [0, 65536]] as [$min, $max]) {
                foreach ([false, true] as $sensitive) {
                    $parser = (new ReadWhileSet($set, $min, $max))->setCaseSensitivity($sensitive);
                    for ($sample = 0; $sample < 12; ++$sample) {
                        $text = 'x';
                        $seed = hash('sha256', $setIndex . ':' . $sample, true);
                        for ($i = 0; $i < $sample; ++$i) {
                            $text .= $units[ord($seed[$i]) % count($units)];
                        }
                        $source = $utf8 ? new Utf8Source($text) : new ByteSource($text);
                        $fastSession = new ParseSession($source);
                        // A generous finite budget selects the authoritative scalar loop.
                        $scalarSession = new ParseSession($source, limits: new ParseLimits(maxWork: PHP_INT_MAX));
                        $fast = $parser->recognizeInSession($source, 1, $fastSession);
                        $scalar = $parser->recognizeInSession($source, 1, $scalarSession);
                        $this->assertSame(
                            [$scalar->accepted, $scalar->length, $scalarSession->workUsed()],
                            [$fast->accepted, $fast->length, $fastSession->workUsed()],
                        );
                        $this->assertSame($scalar->span->rawText(), $fast->span->rawText());
                        $this->assertSame($scalar->diagnostic?->byteOffset, $fast->diagnostic?->byteOffset);
                    }
                }
            }
        }
    }
});

test('regex ranges preserve code point octet and open-ended bounds', function (): void {
    $cases = [
        [new Utf8Source('xαβγω!'), 'α', 'ω'],
        [new Utf8Source('x🙂🙃x'), '🙂', '🙃'],
        [new Utf8Source('xabc0'), 'a', null],
        [new Utf8Source('x09!'), null, '9'],
        [new Utf8Source("x\u{10ffff}!"), "\u{10ffff}", null],
        [new ByteSource("x\0\x80\xff!"), "\0", "\xff"],
        [new ByteSource('xABC!'), 'a', 'z'],
    ];
    foreach ($cases as [$source, $first, $last]) {
        foreach ([[0, null], [0, 0], [1, 1], [2, 5], [0, 65536]] as [$min, $max]) {
            foreach ([false, true] as $sensitive) {
                $parser = (new ReadWhileRange($first, $last, $min, $max))->setCaseSensitivity($sensitive);
                $fastSession = new ParseSession($source);
                $scalarSession = new ParseSession($source, limits: new ParseLimits(maxWork: PHP_INT_MAX));
                $fast = $parser->recognizeInSession($source, 1, $fastSession);
                $scalar = $parser->recognizeInSession($source, 1, $scalarSession);
                $this->assertSame(
                    [$scalar->accepted, $scalar->length, $scalarSession->workUsed()],
                    [$fast->accepted, $fast->length, $fastSession->workUsed()],
                );
            }
        }
    }
});

test('regex literal scans preserve partial delimiters stopping positions and lookahead work', function (): void {
    foreach ([';', '*/', 'aba', "\0", "\r\n", '🙂!', '~[]^\\', 'ab🙂'] as $delimiter) {
        foreach (['', 'x', 'é', '🙂é', 'aaba', "line\n", $delimiter, 'ab', 'ab🙂', 'é' . $delimiter] as $prefix) {
            foreach (['', $delimiter, substr($delimiter, 0, -1)] as $suffix) {
                $text = 'x' . $prefix . $suffix;
                foreach ([false, true] as $utf8) {
                    if ($utf8 && !mb_check_encoding($text, 'UTF-8')) {
                        continue;
                    }
                    $source = $utf8 ? new Utf8Source($text) : new ByteSource($text);
                    foreach ([[0, null], [0, 0], [1, 1], [2, 5], [0, 65536]] as [$min, $max]) {
                        $parser = new ReadUntil($delimiter, $min, $max);
                        $fastSession = new ParseSession($source);
                        $scalarSession = new ParseSession($source, limits: new ParseLimits(maxWork: PHP_INT_MAX));
                        $fast = $parser->recognizeInSession($source, 1, $fastSession);
                        $scalar = $parser->recognizeInSession($source, 1, $scalarSession);
                        $this->assertSame(
                            [$scalar->accepted, $scalar->length, $scalarSession->workUsed()],
                            [$fast->accepted, $fast->length, $fastSession->workUsed()],
                            bin2hex($text . $delimiter),
                        );
                        $this->assertSame($scalar->diagnostic?->byteOffset, $fast->diagnostic?->byteOffset);
                    }
                }
            }
        }
    }
});

test('finite work budgets stop scanning at the precise source unit', function (): void {
    foreach ([new ReadWhileSet('é'), new ReadWhileRange('é', 'é')] as $parser) {
        try {
            $parser->matchPrefix(new Utf8Source('ééé!'), limits: new ParseLimits(maxWork: 3));
            $this->fail('Expected work exhaustion');
        } catch (ParseLimitException $error) {
            $this->assertSame(4, $error->byteOffset);
        }
    }
});

test('generated regex limits fall back without rejecting recognized input', function (): void {
    $this->assertSame('aaa', (new ReadWhileSet(str_repeat('a', 20000)))->parseAll(new Utf8Source('aaa'))->value());
    $delimiter = 'a' . str_repeat('b', 65536);
    $this->assertSame('aaa', (new ReadUntil($delimiter))->parseAll(new Utf8Source('aaa'))->value());
});
