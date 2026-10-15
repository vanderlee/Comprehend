<?php

namespace Tests\Src\Directive;

use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Parser\Terminal\Text;
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
        $this->assertInstanceOf(Space::class, new Space(new Text(' '), new Text('x')));
    }

    public function testConstructorNull()
    {
        $this->assertInstanceOf(Space::class, new Space(null, new Text('x')));
    }

    /**
     * @dataProvider spaceData
     *
     * @param Space $parser
     * @param       $input
     * @param       $offset
     * @param       $match
     * @param       $length
     */
    public function testSpace(Space $parser, $input, $offset, $match, $length)
    {
        $this->assertResult($match, $length, $parser->matchPrefix(new ByteSource($input), $offset), (string) $parser);
    }

    public function spaceData()
    {
        $spacer = new Repeat(new Text('-'), 0, 1);

        return [
            [new Space($spacer, new Sequence(new Text('a'), new Text('b'))), 'ab', 0, true, 2],
            [new Space($spacer, new Sequence(new Text('a'), new Text('b'))), 'a-b', 0, true, 3],
            [new Space($spacer, new Sequence(new Text('a'), new Text('b'))), 'a-b-', 0, true, 3],
            [new Space($spacer, new Sequence(new Text('a'), new Text('b'))), '-a-b-', 0, false, 0],
            [
                new Space(
                    $spacer,
                    new Sequence(
                        new Text('a'),
                        new Space(null, new Sequence(new Text('b'), new Text('c'))),
                        new Text('d'),
                    ),
                ),
                'a-b-c-d',
                0,
                false,
                3,
            ],
            [
                new Space(
                    $spacer,
                    new Sequence(
                        new Text('a'),
                        new Space(null, new Sequence(new Text('b'), new Text('c'))),
                        new Text('d'),
                    ),
                ),
                'a-bc-d',
                0,
                true,
                6,
            ],
            [
                new Space(
                    $spacer,
                    new Sequence(
                        new Text('a'),
                        new Space(null, new Sequence(new Text('b'), new Text('c'))),
                        new Text('d'),
                    ),
                ),
                'abcd',
                0,
                true,
                4,
            ],
            [
                new Space(
                    $spacer,
                    new Sequence(
                        new Text('a'),
                        new Space(null, new Sequence(new Text('b'), new Text('c'))),
                        new Text('d'),
                    ),
                ),
                'ab-cd',
                0,
                false,
                2,
            ],
            [
                new Space(
                    $spacer,
                    new Sequence(
                        new Text('a'),
                        (new Sequence(new Text('b'), new Text('c')))->spacing(null),
                        new Text('d'),
                    ),
                ),
                'a-b-c-d',
                0,
                false,
                3,
            ],
            [
                new Space(
                    $spacer,
                    new Sequence(
                        new Text('a'),
                        (new Sequence(new Text('b'), new Text('c')))->spacing(null),
                        new Text('d'),
                    ),
                ),
                'a-bc-d',
                0,
                true,
                6,
            ],
            [
                new Space(
                    $spacer,
                    new Sequence(
                        new Text('a'),
                        (new Sequence(new Text('b'), new Text('c')))->spacing(null),
                        new Text('d'),
                    ),
                ),
                'abcd',
                0,
                true,
                4,
            ],
            [
                new Space(
                    $spacer,
                    new Sequence(
                        new Text('a'),
                        (new Sequence(new Text('b'), new Text('c')))->spacing(null),
                        new Text('d'),
                    ),
                ),
                'ab-cd',
                0,
                false,
                2,
            ],
            [
                new Space(
                    $spacer,
                    new Sequence(
                        new Text('a'),
                        (new Sequence(new Text('b'), new Text('c')))->spacing(null),
                        new Text('d'),
                    ),
                ),
                'a-b-c-d',
                0,
                false,
                3,
            ],
            [
                new Space(
                    $spacer,
                    new Sequence(
                        new Text('a'),
                        (new Sequence(new Text('b'), new Text('c')))->spacing(null),
                        new Text('d'),
                    ),
                ),
                'a-bc-d',
                0,
                true,
                6,
            ],
            [
                new Space(
                    $spacer,
                    new Sequence(
                        new Text('a'),
                        (new Sequence(new Text('b'), new Text('c')))->spacing(null),
                        new Text('d'),
                    ),
                ),
                'abcd',
                0,
                true,
                4,
            ],
            [
                new Space(
                    $spacer,
                    new Sequence(
                        new Text('a'),
                        (new Sequence(new Text('b'), new Text('c')))->spacing(null),
                        new Text('d'),
                    ),
                ),
                'ab-cd',
                0,
                false,
                2,
            ],
            [
                new Space(
                    $spacer,
                    new Sequence(new Text('a'), new Sequence(new Text('b'), new Text('c')), new Text('d')),
                ),
                'a-b-c-d',
                0,
                true,
                7,
            ],
            [
                new Space(
                    $spacer,
                    new Sequence(new Text('a'), new Sequence(new Text('b'), new Text('c')), new Text('d')),
                ),
                'a-bc-d',
                0,
                true,
                6,
            ],
            [
                new Space(
                    $spacer,
                    new Sequence(new Text('a'), new Sequence(new Text('b'), new Text('c')), new Text('d')),
                ),
                'abcd',
                0,
                true,
                4,
            ],
            [
                new Space(
                    $spacer,
                    new Sequence(new Text('a'), new Sequence(new Text('b'), new Text('c')), new Text('d')),
                ),
                'ab-cd',
                0,
                true,
                5,
            ],
            [
                new Space(
                    $spacer,
                    (new Sequence(
                        new Text('a'),
                        new Sequence(new Text('b'), new Text('c')),
                        new Text('d'),
                    ))->spacing(null),
                ),
                'a-b-c-d',
                0,
                false,
                1,
            ],
            [
                new Space(
                    $spacer,
                    (new Sequence(
                        new Text('a'),
                        new Sequence(new Text('b'), new Text('c')),
                        new Text('d'),
                    ))->spacing(null),
                ),
                'ab-cd',
                0,
                false,
                1,
            ],
            [
                new Space(null, new Sequence(new Text('a'), new Sequence(new Text('b'), new Text('c')))),
                'abcd',
                0,
                true,
                3,
            ],
        ];
    }
}
