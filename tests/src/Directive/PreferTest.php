<?php

namespace Tests\Src\Directive;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Directive\Prefer;
use Vanderlee\Comprehend\Parser\Structure\Choice;

/**
 * @group directive
 */
class PreferTest extends ParserTestCase
{
    public function testConstructor()
    {
        /* @noinspection PhpParamsInspection */
        $this->assertInstanceOf(Prefer::class, new Prefer(Prefer::FIRST, 'a'));
    }

    public function testEmpty()
    {
        $this->expectExceptionMessage('Invalid preference');
        new Prefer(null, 'x');
    }

    /**
     * @dataProvider preferData
     *
     * @param Prefer $parser
     * @param        $input
     * @param        $offset
     * @param        $match
     * @param        $length
     */
    public function testPrefer(Prefer $parser, $input, $offset, $match, $length)
    {
        $this->assertResult($match, $length, $parser->match($input, $offset), (string) $parser);
    }

    public function preferData()
    {
        return [
            [new Prefer(Prefer::FIRST, new Choice('a', 'aa')), 'aa', 0, true, 1],
            [new Prefer(Prefer::FIRST, new Choice('aa', 'a')), 'aa', 0, true, 2],
            [new Prefer(Prefer::LONGEST, new Choice('a', 'aa')), 'aa', 0, true, 2],
            [new Prefer(Prefer::LONGEST, new Choice('aa', 'a')), 'aa', 0, true, 2],
            [new Prefer(Prefer::SHORTEST, new Choice('a', 'aa')), 'aa', 0, true, 1],
            [new Prefer(Prefer::SHORTEST, new Choice('aa', 'a')), 'aa', 0, true, 1],
        ];
    }
}
