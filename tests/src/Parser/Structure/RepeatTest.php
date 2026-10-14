<?php

namespace Tests\Src\Parser\Structure;

use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\ByteSource;
use InvalidArgumentException;
use LogicException;
use Tests\ParserTestCase;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Not;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Text;
/**
 * @group structure
 * @group parser
 */
class RepeatTest extends ParserTestCase
{
    public function testConstructor()
    {
        $this->assertInstanceOf(Repeat::class, new Repeat(new Text('a')));
    }
    /**
     * @dataProvider invalidBoundsData
     */
    public function testInvalidBounds($min, $max)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid repeat range specified');
        new Repeat(new Text('a'), $min, $max);
    }
    public function invalidBoundsData()
    {
        return [
            'minimum exceeds maximum' => [2, 1],
            'negative unbounded minimum' => [-1, null],
            'negative bounded minimum' => [-1, 2],
            'negative maximum' => [0, -1],
            'both negative in ascending order' => [-2, -1],
        ];
    }
    public function testNegativeExactCount()
    {
        $this->expectException(InvalidArgumentException::class);
        Repeat::exact(new Text('a'), -1);
    }
    public function testStaticConstructors()
    {
        $c = Repeat::plus(new Text('a'));
        $this->assertResult(false, 0, $c->matchPrefix(new ByteSource('')));
        $this->assertResult(true, 1, $c->matchPrefix(new ByteSource('a')));
        $this->assertResult(true, 2, $c->matchPrefix(new ByteSource('aa')));
        $c = Repeat::star(new Text('a'));
        $this->assertResult(true, 0, $c->matchPrefix(new ByteSource('')));
        $this->assertResult(true, 1, $c->matchPrefix(new ByteSource('a')));
        $this->assertResult(true, 2, $c->matchPrefix(new ByteSource('aa')));
        $c = Repeat::optional(new Text('a'));
        $this->assertResult(true, 0, $c->matchPrefix(new ByteSource('')));
        $this->assertResult(true, 1, $c->matchPrefix(new ByteSource('a')));
        $this->assertResult(true, 1, $c->matchPrefix(new ByteSource('aa')));
        $c = Repeat::exact(new Text('a'), 2);
        $this->assertResult(false, 0, $c->matchPrefix(new ByteSource('')));
        $this->assertResult(false, 1, $c->matchPrefix(new ByteSource('a')));
        $this->assertResult(true, 2, $c->matchPrefix(new ByteSource('aa')));
        $this->assertResult(true, 2, $c->matchPrefix(new ByteSource('aaa')));
        $c = Repeat::exact(new Text('a'), 0);
        $this->assertResult(true, 0, $c->matchPrefix(new ByteSource('')));
        $this->assertResult(true, 0, $c->matchPrefix(new ByteSource('a')));
    }
    /**
     * @dataProvider zeroLengthChildData
     */
    public function testZeroLengthChildThrows(Repeat $parser, $input)
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Repeat child matched successfully without consuming input');
        $parser->matchPrefix(new ByteSource($input));
    }
    public function zeroLengthChildData()
    {
        $cases = [];
        foreach ([1, 3, null] as $max) {
            $label = $max === null ? 'unbounded' : 'maximum ' . $max;
            $cases[$label . ' Nothing'] = [new Repeat(new Nothing(), 0, $max), 'a'];
            $cases[$label . ' lookahead'] = [new Repeat(new Not(new Text('b')), 0, $max), 'a'];
            $cases[$label . ' nullable optional'] = [new Repeat(Repeat::optional(new Text('b')), 0, $max), 'a'];
        }
        $cases['positive minimum'] = [new Repeat(new Not(new Text('b')), 1), 'a'];
        $cases['nullable after consuming child'] = [new Repeat(new Choice(new Text('a'), new Not(new Text('b')))), 'a'];
        $cases['spacing cannot supply child progress'] = [(new Repeat(new Choice(new Text('a'), new Not(new Text('b'))), 0, 2))->spacing(new Text(' ')), 'a c'];
        $cases['custom nullable parser'] = [
            new Repeat(
                new class('a') extends Text
                {
                    protected function recognize(Source $input, int $offset, ParseSession $context): AbstractMatch
                    {
                        return $this->success($input, $offset);
                    }
                },
            ),
            'a',
        ];
        return $cases;
    }
    public function testZeroMaximumAllowsNullableChild()
    {
        $this->assertResult(true, 0, (new Repeat(new Nothing(), 0, 0))->matchPrefix(new ByteSource('a')));
    }
    public function testZeroLengthChildCallbackIsNotResolved()
    {
        $calls = 0;
        $child = (new Not(new Text('b')))->onCommit(
            function () use (&$calls) {
                $calls++;
            },
        );
        try {
            (new Repeat($child, 0, 1))->matchPrefix(new ByteSource('a'));
            $this->fail('Expected zero-length repetition to throw');
        } catch (LogicException $exception) {
            $this->assertSame(0, $calls);
        }
    }
    /**
     * @dataProvider repeatData
     *
     * @param Repeat $parser
     * @param        $input
     * @param        $offset
     * @param        $match
     * @param        $length
     */
    public function testRepeat(Repeat $parser, $input, $offset, $match, $length)
    {
        $result = $parser->matchPrefix(new ByteSource($input), $offset);
        $this->assertSame($match, $result->success, (string) $parser);
        $this->assertSame($match ? $length : 0, $result->consumedByteLength, (string) $parser);
    }
    public function repeatData()
    {
        return [
            [new Repeat(new Text('a'), 0, 0), '', 0, true, 0],
            [new Repeat(new Text('a'), 0, 0), 'a', 0, true, 0],
            [new Repeat(new Text('a'), 0, 0), 'b', 0, true, 0],
            [new Repeat(new Text('a'), 0, 0), 'ba', 1, true, 0],
            [new Repeat(new Text('a'), 2, 4), 'b', 0, false, 0],
            [new Repeat(new Text('a'), 2, 4), '', 0, false, 0],
            [new Repeat(new Text('a'), 2, 4), 'a', 0, false, 1],
            [new Repeat(new Text('a'), 2, 4), 'aa', 0, true, 2],
            [new Repeat(new Text('a'), 2, 4), 'aaa', 0, true, 3],
            [new Repeat(new Text('a'), 2, 4), 'aaaa', 0, true, 4],
            [new Repeat(new Text('a'), 2, 4), 'aaaaa', 0, true, 4],
            [new Repeat(new Text('a'), 0, 1), '', 0, true, 0],
            [new Repeat(new Text('a'), 0, 1), 'a', 0, true, 1],
            [new Repeat(new Text('a'), 0, 1), 'aa', 0, true, 1],
            [new Repeat(new Text('a'), 0, 2), 'aa', 0, true, 2],
            [new Repeat(new Text('a'), 0, 2), 'aaa', 0, true, 2],
            [new Repeat(new Text('a'), 1, 1), 'aaa', 0, true, 1],
            [new Repeat(new Text('a'), 1, 2), 'aaa', 0, true, 2],
            [new Repeat(new Text('a'), 2, 2), 'a', 0, false, 1],
            [new Repeat(new Text('a'), 2, 2), 'aa', 0, true, 2],
            [new Repeat(new Text('a'), 2, 2), 'aaa', 0, true, 2],
            [new Repeat(new Text('a'), 0), '', 0, true, 0],
            [new Repeat(new Text('a'), 0), 'a', 0, true, 1],
            [new Repeat(new Text('a'), 2), 'a', 0, false, 1],
            [new Repeat(new Text('a'), 2), 'aa', 0, true, 2],
            [new Repeat(new Text('a'), 2), 'aaa', 0, true, 3],
            [new Repeat(new Text('a'), 2), 'aaaa', 0, true, 4],
            [new Repeat(new Text('ab'), 2, 2), 'ab', 0, false, 2],
            [new Repeat(new Text('ab'), 2, 2), 'aba', 0, false, 2],
            [new Repeat(new Text('ab'), 2, 2), 'abab', 0, true, 4],
            [new Repeat(new Text('ab'), 2, 2), 'ababa', 0, true, 4],
            [new Repeat(new Text('ab'), 2, 2), 'ababab', 0, true, 4],
        ];
    }
}
