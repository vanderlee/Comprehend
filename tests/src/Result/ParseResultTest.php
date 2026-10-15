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
    /** @dataProvider failureData */
    public function failureData()
    {
        return [
            [new Text('abc'), 'zabx', 1, 3],
            [new Text('abc'), 'zab', 1, 3],
            [Choice::shortest(new Text('abcd'), new Text('az')), 'zabcx', 1, 4],
            [new Sequence(new Text('a'), new Text('bc'), new End()), 'za b', 1, 2],
            [(new Sequence(new Text('a'), new Text('b')))->spacing(new Text(' -')), 'a x', 0, 2],
            [new Text("éb"), "zéa", 1, 3],
        ];
    }

    public function testSuccessfulResultCanCarrySeparateSpeculativeFailurePosition()
    {
        $result = Choice::first(new Text('abcd'), new Text('a'))->matchPrefix(new Utf8Source('abcx'));
        $this->assertSame([0, 1, 1], [$result->start, $result->end, $result->consumedByteLength]);
        $this->assertSame(3, $result->farthestFailure);
        $result = (new Repeat(new Text('a')))->matchPrefix(new Utf8Source('aa'));
        $this->assertTrue($result->success);
        $this->assertSame(2, $result->consumedByteLength);
        $this->assertNull($result->farthestFailure);
    }
}
