<?php

namespace Tests\Src\Directive;

use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Tests\ParserTestCase;
use Vanderlee\Comprehend\Directive\CaseSensitive;

/**
 * @group directive
 */
class CaseSensitiveTest extends ParserTestCase
{
    public function testConstructor()
    {
        $this->assertInstanceOf(CaseSensitive::class, new CaseSensitive(true, new Text('a')));
    }
    /**
     * @dataProvider caseSensitiveData
     *
     * @param CaseSensitive $parser
     * @param string $input
     * @param int $offset
     * @param bool $match
     * @param int $length
     */
    public function testCaseSensitive(CaseSensitive $parser, $input, $offset, $match, $length)
    {
        $this->assertResult($match, $length, $parser->matchPrefix(new ByteSource($input), $offset), (string) $parser);
    }
    public function caseSensitiveData()
    {
        return [
            [new CaseSensitive(true, new Text('foo')), 'foo', 0, true, 3],
            [new CaseSensitive(true, new Text('foo')), 'fOO', 0, false, 1],
            [new CaseSensitive(false, new Text('foo')), 'foo', 0, true, 3],
            [new CaseSensitive(false, new Text('foo')), 'fOO', 0, true, 3],
            [new CaseSensitive(false, new Text('FOO')), 'foo', 0, true, 3],
        ];
    }
}
