<?php

namespace Tests\Src\Parser\Structure;

use InvalidArgumentException;
use Tests\ParserTestCase;
use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Terminal\Text;

/**
 * @group structure
 * @group parser
 */
class RepeatTest extends ParserTestCase
{
    public function testConstructor()
    {
        $this->assertInstanceOf(Repeat::class, new Repeat('a'));
    }

    /**
     * @dataProvider invalidBoundsData
     */
    public function testInvalidBounds($min, $max)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid repeat range specified');
        new Repeat('a', $min, $max);
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
        Repeat::exact('a', -1);
    }

    public function testZeroMaximumDoesNotInvokeChild()
    {
        $child = new class('a') extends Text {
            protected function parse(&$input, $offset, Context $context)
            {
                throw new \LogicException('Child must not be invoked');
            }
        };

        $result = (new Repeat($child, 0, 0))->match('ba', 1);
        $this->assertResult(true, 0, $result);
        $this->assertSame(1, $result->token->offset);
        $this->assertSame('', $result->token->text);
        $this->assertSame([], $result->token->children);
    }

    public function testStaticConstructors()
    {
        $c = Repeat::plus('a');
        $this->assertResult(false, 0, $c->match(''));
        $this->assertResult(true, 1, $c->match('a'));
        $this->assertResult(true, 2, $c->match('aa'));

        $c = Repeat::star('a');
        $this->assertResult(true, 0, $c->match(''));
        $this->assertResult(true, 1, $c->match('a'));
        $this->assertResult(true, 2, $c->match('aa'));

        $c = Repeat::optional('a');
        $this->assertResult(true, 0, $c->match(''));
        $this->assertResult(true, 1, $c->match('a'));
        $this->assertResult(true, 1, $c->match('aa'));

        $c = Repeat::exact('a', 2);
        $this->assertResult(false, 0, $c->match(''));
        $this->assertResult(false, 1, $c->match('a'));
        $this->assertResult(true, 2, $c->match('aa'));
        $this->assertResult(true, 2, $c->match('aaa'));

        $c = Repeat::exact('a', 0);
        $this->assertResult(true, 0, $c->match(''));
        $this->assertResult(true, 0, $c->match('a'));
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
        $result = $parser->match($input, $offset);

        $this->assertSame($match, $result->match, (string)$parser);
        $this->assertSame($length, $result->length, (string)$parser);
    }

    public function repeatData()
    {
        return [
            [new Repeat('a', 0, 0), '', 0, true, 0],
            [new Repeat('a', 0, 0), 'a', 0, true, 0],
            [new Repeat('a', 0, 0), 'b', 0, true, 0],
            [new Repeat('a', 0, 0), 'ba', 1, true, 0],
            [new Repeat('a', 2, 4), 'b', 0, false, 0],

            [new Repeat('a', 2, 4), '', 0, false, 0],
            [new Repeat('a', 2, 4), 'a', 0, false, 1],
            [new Repeat('a', 2, 4), 'aa', 0, true, 2],
            [new Repeat('a', 2, 4), 'aaa', 0, true, 3],
            [new Repeat('a', 2, 4), 'aaaa', 0, true, 4],
            [new Repeat('a', 2, 4), 'aaaaa', 0, true, 4],

            [new Repeat('a', 0, 1), '', 0, true, 0],
            [new Repeat('a', 0, 1), 'a', 0, true, 1],
            [new Repeat('a', 0, 1), 'aa', 0, true, 1],
            [new Repeat('a', 0, 2), 'aa', 0, true, 2],
            [new Repeat('a', 0, 2), 'aaa', 0, true, 2],
            [new Repeat('a', 1, 1), 'aaa', 0, true, 1],
            [new Repeat('a', 1, 2), 'aaa', 0, true, 2],

            [new Repeat('a', 2, 2), 'a', 0, false, 1],
            [new Repeat('a', 2, 2), 'aa', 0, true, 2],
            [new Repeat('a', 2, 2), 'aaa', 0, true, 2],

            [new Repeat('a', 0), '', 0, true, 0],
            [new Repeat('a', 0), 'a', 0, true, 1],
            [new Repeat('a', 2), 'a', 0, false, 1],
            [new Repeat('a', 2), 'aa', 0, true, 2],
            [new Repeat('a', 2), 'aaa', 0, true, 3],
            [new Repeat('a', 2), 'aaaa', 0, true, 4],

            [new Repeat('ab', 2, 2), 'ab', 0, false, 2],
            [new Repeat('ab', 2, 2), 'aba', 0, false, 2],
            [new Repeat('ab', 2, 2), 'abab', 0, true, 4],
            [new Repeat('ab', 2, 2), 'ababa', 0, true, 4],
            [new Repeat('ab', 2, 2), 'ababab', 0, true, 4],
        ];
    }
}
