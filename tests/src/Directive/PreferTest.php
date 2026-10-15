<?php

namespace Tests\Src\Directive;

use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Parser\Terminal\Text;
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
        $this->assertInstanceOf(Prefer::class, new Prefer(Prefer::FIRST, new Text('a')));
    }

    public function testEmpty()
    {
        $this->expectException(\TypeError::class);
        new Prefer(null, new Text('x'));
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
        $this->assertResult($match, $length, $parser->matchPrefix(new ByteSource($input), $offset), (string) $parser);
    }

    public function preferData()
    {
        return [
            [new Prefer(Prefer::FIRST, new Choice(new Text('a'), new Text('aa'))), 'aa', 0, true, 1],
            [new Prefer(Prefer::FIRST, new Choice(new Text('aa'), new Text('a'))), 'aa', 0, true, 2],
            [new Prefer(Prefer::LONGEST, new Choice(new Text('a'), new Text('aa'))), 'aa', 0, true, 2],
            [new Prefer(Prefer::LONGEST, new Choice(new Text('aa'), new Text('a'))), 'aa', 0, true, 2],
            [new Prefer(Prefer::SHORTEST, new Choice(new Text('a'), new Text('aa'))), 'aa', 0, true, 1],
            [new Prefer(Prefer::SHORTEST, new Choice(new Text('aa'), new Text('a'))), 'aa', 0, true, 1],
        ];
    }
}
