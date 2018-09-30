<?php

namespace Tests\Src\Directive;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Directive\Space;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;

/**
 * @group directive
 */
class SpaceTest extends ParserTestCase
{
    public function testConstructor()
    {
        $this->assertInstanceOf(Space::class, new Space(' ', 'x'));
    }

    /**
     * @dataProvider spaceData
     *
     * @param Space $parser
     * @param $input
     * @param $offset
     * @param $match
     * @param $length
     */
    public function testSpace(Space $parser, $input, $offset, $match, $length)
    {
        $this->assertResult($match, $length, $parser->match($input, $offset), (string)$parser);
    }

    public function spaceData()
    {
        $spacer = new Repeat('-', 0, 1);

        return [
            [new Space($spacer, new Sequence('a', 'b')), 'ab', 0, true, 2],
            [new Space($spacer, new Sequence('a', 'b')), 'a-b', 0, true, 3],
            [new Space($spacer, new Sequence('a', 'b')), 'a-b-', 0, true, 3],
            [new Space($spacer, new Sequence('a', 'b')), '-a-b-', 0, false, 0],

            [
                new Space($spacer, new Sequence('a', new Space(null, new Sequence('b', 'c')), 'd')),
                'a-b-c-d',
                0,
                false,
                3
            ],
            [new Space($spacer, new Sequence('a', new Space(null, new Sequence('b', 'c')), 'd')), 'a-bc-d', 0, true, 6],
            [new Space($spacer, new Sequence('a', new Space(null, new Sequence('b', 'c')), 'd')), 'abcd', 0, true, 4],
            [new Space($spacer, new Sequence('a', new Space(null, new Sequence('b', 'c')), 'd')), 'ab-cd', 0, false, 2],

            [
                new Space($spacer, new Sequence('a', (new Sequence('b', 'c'))->spacing(null), 'd')),
                'a-b-c-d',
                0,
                false,
                3
            ],
            [new Space($spacer, new Sequence('a', (new Sequence('b', 'c'))->spacing(null), 'd')), 'a-bc-d', 0, true, 6],
            [new Space($spacer, new Sequence('a', (new Sequence('b', 'c'))->spacing(null), 'd')), 'abcd', 0, true, 4],
            [new Space($spacer, new Sequence('a', (new Sequence('b', 'c'))->spacing(null), 'd')), 'ab-cd', 0, false, 2],

            [
                new Space($spacer, new Sequence('a', (new Sequence('b', 'c'))->spacing(false), 'd')),
                'a-b-c-d',
                0,
                false,
                3
            ],
            [
                new Space($spacer, new Sequence('a', (new Sequence('b', 'c'))->spacing(false), 'd')),
                'a-bc-d',
                0,
                true,
                6
            ],
            [new Space($spacer, new Sequence('a', (new Sequence('b', 'c'))->spacing(false), 'd')), 'abcd', 0, true, 4],
            [
                new Space($spacer, new Sequence('a', (new Sequence('b', 'c'))->spacing(false), 'd')),
                'ab-cd',
                0,
                false,
                2
            ],

            [
                new Space($spacer, new Sequence('a', (new Sequence('b', 'c'))->spacing(true), 'd')),
                'a-b-c-d',
                0,
                true,
                7
            ],
            [new Space($spacer, new Sequence('a', (new Sequence('b', 'c'))->spacing(true), 'd')), 'a-bc-d', 0, true, 6],
            [new Space($spacer, new Sequence('a', (new Sequence('b', 'c'))->spacing(true), 'd')), 'abcd', 0, true, 4],
            [new Space($spacer, new Sequence('a', (new Sequence('b', 'c'))->spacing(true), 'd')), 'ab-cd', 0, true, 5],

            [
                new Space($spacer, (new Sequence('a', (new Sequence('b', 'c'))->spacing(true), 'd'))->spacing(false)),
                'a-b-c-d',
                0,
                false,
                1
            ],
            [
                new Space($spacer, (new Sequence('a', (new Sequence('b', 'c'))->spacing(true), 'd'))->spacing(false)),
                'ab-cd',
                0,
                true,
                5
            ],
        ];
    }

}
