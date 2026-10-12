<?php

declare(strict_types=1);

namespace Tests\Src\Result;

use InvalidArgumentException;
use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\End;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Source\Utf8Source;

class ParseResultTest extends ParserTestCase
{
    public function testCanonicalSpanAndLazyProjectionsRetainOriginalSource()
    {
        $source = new Utf8Source("z\u{00E9}a");
        $calls = 0;
        $maps = 0;
        $parser = (new Text("\u{00E9}a"))->token('word')->setResult('word', function ($text) use (&$maps) {
            $maps++;
            return $text;
        })->callback(function () use (&$calls) { $calls++; });
        $result = $parser->parseSource($source, 1);
        $this->assertTrue($result->success);
        $this->assertSame($source, $result->span->source);
        $this->assertSame([1, 4, 3], [$result->start, $result->end, $result->consumedByteLength]);
        $this->assertNull($result->farthestFailure);
        $this->assertSame("\u{00E9}a", $result->rawText());
        $this->assertSame(1, $calls);
        $this->assertSame(0, $maps);
        $this->assertSame(['word' => "\u{00E9}a"], $result->results());
        $this->assertSame("\u{00E9}a", $result->result('word'));
        $this->assertSame(1, $maps);
        $this->assertSame(3, $result->token()->length);
        $this->assertSame(1, $calls);
    }

    /** @dataProvider failureData */
    public function testFailuresDoNotConsumeDiagnosticProgress($parser, $text, $start, $farthest)
    {
        $source = new Utf8Source($text);
        $result = $parser->parseSource($source, $start);
        $this->assertFalse($result->success);
        $this->assertSame($source, $result->span->source);
        $this->assertSame([$start, $start, 0], [$result->start, $result->end, $result->consumedByteLength]);
        $this->assertSame($farthest, $result->farthestFailure);
        $this->assertSame('', $result->rawText());
        $this->assertNull($result->token());
        $this->assertSame([], $result->results());
        $this->assertSame('fallback', $result->result('missing', 'fallback'));
    }

    public function failureData()
    {
        return [
            [new Text('abc'), 'zabx', 1, 3],
            [new Text('abc'), 'zab', 1, 3],
            [Choice::shortest('abcd', 'az'), 'zabcx', 1, 4],
            [new Sequence('a', 'bc', new End()), 'za b', 1, 2],
            [(new Sequence('a', 'b'))->spacing(new Text(' -')), 'a x', 0, 2],
            [new Text("\u{00E9}b"), "z\u{00E9}a", 1, 3],
        ];
    }

    public function testSuccessfulResultCanCarrySeparateSpeculativeFailurePosition()
    {
        $result = Choice::first('abcd', 'a')->parseSource(new Utf8Source('abcx'));
        $this->assertSame([0, 1, 1], [$result->start, $result->end, $result->consumedByteLength]);
        $this->assertSame(3, $result->farthestFailure);
        $result = (new Repeat('a'))->parseSource(new Utf8Source('aa'));
        $this->assertTrue($result->success);
        $this->assertSame(2, $result->consumedByteLength);
        $this->assertNull($result->farthestFailure);
    }

    public function testEmptyResultsKeepTheirActualPosition()
    {
        foreach ([new Nothing(), new End()] as $parser) {
            $result = $parser->parseSource(new Utf8Source("\u{00E9}"), 2);
            $this->assertSame([2, 2, 0], [$result->start, $result->end, $result->consumedByteLength]);
            $this->assertSame(2, $result->token()->offset);
        }
        $this->assertSame(0, (new Nothing())->parseSource(new Utf8Source(''))->start);
    }

    public function testOldUnboundMatchCannotBeGuessedIntoCanonicalResult()
    {
        $this->expectException(InvalidArgumentException::class);
        new ParseResult((new Text('a'))->match('a'));
    }
}
