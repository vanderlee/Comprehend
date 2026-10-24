<?php

declare(strict_types=1);

namespace Tests\Extensions\Optimizer;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Extensions\Optimizer\GraphOptimizer;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadWhileSet;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadWhileRange;
use Tests\Fixtures\SingleUnitSource;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Not;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Set;
use Vanderlee\Comprehend\Parser\Terminal\Any;
use Vanderlee\Comprehend\Parser\Terminal\Char;
use Vanderlee\Comprehend\Parser\Terminal\Range;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ParseMetrics;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\ByteFileSource;
use Vanderlee\Comprehend\Source\Utf8Source;
use Vanderlee\Comprehend\Source\Utf8FileSource;
use Vanderlee\Comprehend\Source\Span;

uses(TestCase::class);

test('freeze rewrites shared repeated sets without changing authoring graphs or concrete trees', function () {
    $repeat = Repeat::star(new Set('ab'));
    $graph = new Sequence($repeat, $repeat);
    $optimized = (new GraphSnapshot(optimize: true))->copy($graph);
    $this->assertSame($optimized->children()[0], $optimized->children()[1]);
    $source = new ByteSource(str_repeat('ab', 30));
    $counts = [];
    foreach ([$graph, (new GraphSnapshot())->copy($graph), $optimized] as $parser) {
        $metrics = new ParseMetrics();
        $match = $parser->recognizeInSession($source, 0, new ParseSession($source, metrics: $metrics));
        $this->assertEquals($graph->parseAll($source)->node, $match->node);
        $counts[] = $metrics->snapshot()['parser_entries'];
    }
    $this->assertSame($counts[0], $counts[1]);
    $this->assertLessThan($counts[0], $counts[2]);
});

test('replacement configuration stays lazy and snapshots remain reusable and isolated', function (
    Parser $parser,
    string $input,
): void {
    $mapped = 0;
    $committed = 0;
    $parser->name('result', 'numbers')->tag('selected')
        ->guard(static fn(): bool => true)
        ->mapValue(static function (ParseResult $result) use (&$mapped): string {
            ++$mapped;
            return $result->rawText();
        })
        ->onCommit(static function () use (&$committed): void {
            ++$committed;
        });
    $snapshot = new GraphSnapshot(optimize: true);
    $copy = $snapshot->copy($parser);
    $plain = (new GraphSnapshot())->copy($copy);
    $this->assertTrue($snapshot->isReusable());
    $this->assertNotSame($parser, $copy);
    $this->assertNotSame($copy, $plain);
    foreach ([$parser, $copy, $plain] as $candidate) {
        $result = $candidate->parseAll(new ByteSource($input));
        $this->assertEquals($parser->parseAll(new ByteSource($input))->node, $result->node);
        $before = $mapped;
        $this->assertSame($input, $result->value());
        $this->assertSame($input, $result->value());
        $this->assertSame($before + 1, $mapped);
        $before = $committed;
        $result->commit();
        $result->commit();
        $this->assertSame($before + 1, $committed);
    }
})->with([
    'repeat' => [Repeat::star(new Set('ab')), 'aba'],
    'choice' => [new Choice(new Text('a'), new Text('b')), 'a'],
    'not' => [new Not(new Text('a')), ''],
]);

test('scanned repetitions retain unit spans trees and diagnostics across source modes', function (
    Parser $operand,
    string $input,
    int $minimum,
    ?int $maximum,
    bool $caseSensitive,
): void {
    $parser = (new Repeat($operand, $minimum, $maximum))->name('run');
    $optimized = (new GraphSnapshot(optimize: true))->copy($parser);
    (new GraphOptimizer())->prepare($optimized);
    foreach ([ByteSource::class, Utf8Source::class, ByteFileSource::class, Utf8FileSource::class] as $class) {
        $stream = null;
        if ($class === ByteFileSource::class || $class === Utf8FileSource::class) {
            $stream = tmpfile();
            fwrite($stream, '>' . $input);
            $source = new $class($stream, pageSize: 4);
        } else {
            $source = new $class('>' . $input);
        }
        try {
            $results = [];
            foreach ([$parser, $optimized] as $candidate) {
                $session = new ParseSession($source, caseSensitive: $caseSensitive);
                $results[] = new ParseResult($candidate->recognizeInSession($source, 1, $session));
            }
            [$ordinary, $scanned] = $results;
            $this->assertSame($ordinary->success, $scanned->success);
            $this->assertSame($ordinary->end, $scanned->end);
            $this->assertEquals($ordinary->node, $scanned->node);
            $this->assertEquals($ordinary->diagnostic, $scanned->diagnostic);
            $this->assertSame($ordinary->farthestFailure, $scanned->farthestFailure);
            $this->assertSame($ordinary->value(), $scanned->value());
            $this->assertEquals($ordinary->ast(), $scanned->ast());
        } finally {
            if ($stream !== null) {
                $source->close();
                fclose($stream);
            }
        }
    }
})->with([
    'ASCII literal across pages' => [new Text('a'), str_repeat('a', 20) . '!', 1, null, true],
    'ASCII char bounded' => [new Char('a'), 'aaaa!', 1, 3, true],
    'ASCII set with NUL' => [new Set("ab\0"), "ab\0ba!", 1, null, true],
    'ASCII range' => [new Range('a', 'z'), 'abcxyz!', 1, null, true],
    'ASCII range with open lower bound' => [new Range(null, 'z'), "\0ab!", 1, null, true],
    'empty run' => [new Text('a'), '!', 0, null, true],
    'minimum failure' => [new Text('a'), 'aa!', 3, null, true],
    'zero maximum' => [new Text('a'), 'aaa', 0, 0, true],
    'any Unicode fallback' => [new Any(), "a\u{e9}\u{20ac}\u{1f642}", 0, null, true],
    'Unicode set fallback' => [new Set("a\u{e9}"), "a\u{e9}a!", 1, null, true],
    'excluded char Unicode fallback' => [new Char('!', false), "a\u{e9}!", 1, null, true],
    'open upper range Unicode fallback' => [new Range('a', null), "a\u{e9}!", 1, null, true],
    'local Unicode case folding' => [(new Set('k'))->caseInsensitive(), "k\u{212a}K!", 0, null, true],
    'inherited Unicode case folding' => [new Set('k'), "k\u{212a}K!", 0, null, false],
    'local case sensitivity overrides scope' => [(new Text('a'))->caseSensitive(), 'aaA', 0, null, false],
]);

test('scanned repetitions preserve custom file source unit hooks', function (): void {
    $stream = tmpfile();
    fwrite($stream, 'aaa');
    $source = new class ($stream, pageSize: 4) extends ByteFileSource {
        public int $reads = 0;

        public function readUnit(int $byteOffset): ?Span
        {
            ++$this->reads;

            return parent::readUnit($byteOffset);
        }
    };
    try {
        $scan = GraphOptimizer::scanRepeat(
            new ReadWhileSet('a'),
            new Text('a'),
            $source,
            0,
            new ParseSession($source),
        );
        $this->assertSame(3, $scan[0]);
        $this->assertSame(7, $source->reads); // Three scan reads, EOF, and three retained child units.
        $this->assertSame([0, 1, 2], array_map(static fn($node): int => $node->span->start, $scan[1]));
        $this->assertSame([1, 2, 3], array_map(static fn($node): int => $node->span->end, $scan[1]));
    } finally {
        $source->close();
        fclose($stream);
    }
});

test('retaining scanned one-byte children adds no recognition work', function (): void {
    $source = new Utf8Source('aaab!');
    $scanner = new ReadWhileSet('ab');
    $session = new ParseSession($source);
    [$accepted, $length] = $scanner->scanLength($source, 0, $session);
    $this->assertTrue($accepted);
    $scanned = new ParseSession($source);
    $result = GraphOptimizer::scanRepeat($scanner, new Set('ab'), $source, 0, $scanned);
    $this->assertSame($length, $result[0]);
    $this->assertSame($session->workUsed(), $scanned->workUsed());
});

test('byte repetitions retain arbitrary octets as separate concrete children', function (): void {
    $input = "\0\xff\xc3\xa9";
    $stream = tmpfile();
    fwrite($stream, $input);
    $file = new ByteFileSource($stream, pageSize: 4);
    try {
        foreach ([new ByteSource($input), $file] as $source) {
            $parser = Repeat::plus(new Any());
            $ordinary = $parser->freeze(0)->parse($source);
            $scanned = $parser->freeze()->parse($source);
            $this->assertTrue($scanned->success);
            $this->assertEquals($ordinary->node->toArray(), $scanned->node->toArray());
            $this->assertSame(str_split($input), array_map(
                static fn($node): string => $node->rawText(),
                $scanned->node->children,
            ));
        }
    } finally {
        $file->close();
        fclose($stream);
    }
});

test('UTF8 repetition chunks preserve each code point across chunk and page boundaries', function (
    string $input,
    ?int $maximum,
): void {
    $stream = tmpfile();
    fwrite($stream, '>' . $input);
    $files = [new Utf8FileSource($stream, pageSize: 4), new Utf8FileSource($stream)];
    try {
        foreach ([new Utf8Source('>' . $input), ...$files] as $source) {
            $parser = (new Repeat(new Any(), max: $maximum))->name('run');
            $ordinary = $parser->freeze(0)->matchPrefix($source, 1);
            $scanned = $parser->freeze()->matchPrefix($source, 1);
            $this->assertTrue($scanned->success);
            $this->assertSame(
                hash('sha256', $ordinary->node->toJson()),
                hash('sha256', $scanned->node->toJson()),
            );
            $this->assertSame($ordinary->ast()->toArray(), $scanned->ast()->toArray());
            $this->assertEquals($ordinary->diagnostic, $scanned->diagnostic);
            $units = mb_str_split($input, 1, 'UTF-8');
            if ($maximum !== null) {
                $units = array_slice($units, 0, $maximum);
            }
            $expected = [];
            $offset = 1;
            foreach ($units as $unit) {
                $expected[] = [$offset, $offset + strlen($unit), $unit];
                $offset += strlen($unit);
            }
            $this->assertSame($offset, $scanned->end);
            $this->assertSame($expected, array_map(
                static fn($node): array => [$node->span->start, $node->span->end, $node->rawText()],
                $scanned->node->children,
            ));
            $this->assertSame(implode('', $units), $scanned->value());
        }
    } finally {
        foreach ($files as $file) {
            $file->close();
        }
        fclose($stream);
    }
})->with([
    'empty' => ['', null],
    'all widths' => ["a\u{e9}\u{20ac}\u{1f642}", null],
    'two byte split' => [str_repeat('a', 8191) . "\u{e9}" . str_repeat("\u{20ac}\u{1f642}", 2000), null],
    'three byte split' => [str_repeat('a', 8190) . "\u{20ac}" . str_repeat("\u{e9}\u{1f642}", 2000), null],
    'four byte split' => [str_repeat('a', 8189) . "\u{1f642}" . str_repeat("\u{e9}\u{20ac}", 2000), null],
    'exact chunk boundary' => [str_repeat('a', 8188) . "\u{1f642}", null],
    'bounded before suffix' => [str_repeat("a\u{e9}\u{20ac}\u{1f642}", 3000), 129],
]);

test('UTF8 chunk construction adds no work and preserves custom source unit hooks', function (): void {
    $input = "a\u{e9}\u{20ac}\u{1f642}";
    foreach ([new Utf8Source($input), new SingleUnitSource(new Utf8Source($input))] as $source) {
        $scanner = new ReadWhileRange("\0", null);
        $reference = new ParseSession($source);
        $scanner->scanLength($source, 0, $reference);
        if ($source instanceof SingleUnitSource) {
            $source->unitReads = 0;
        }
        $session = new ParseSession($source);
        $scan = GraphOptimizer::scanRepeat($scanner, new Any(), $source, 0, $session);
        $this->assertSame(strlen($input), $scan[0]);
        $this->assertSame($reference->workUsed(), $session->workUsed());
        $this->assertSame(mb_str_split($input, 1, 'UTF-8'), array_map(
            static fn($node): string => $node->rawText(),
            $scan[1],
        ));
        if ($source instanceof SingleUnitSource) {
            $this->assertSame(9, $source->unitReads); // Four scan reads, EOF, four retained child units.
        }
    }
});
