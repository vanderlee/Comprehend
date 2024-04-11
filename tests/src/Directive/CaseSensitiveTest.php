<?php

namespace Tests\Src\Directive;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Directive\CaseSensitive;

/**
 * @group directive
 */
class CaseSensitiveTest extends ParserTestCase
{
    public function testConstructor()
    {
        $this->assertInstanceOf(CaseSensitive::class, new CaseSensitive(true, 'a'));
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
        $this->assertResult($match, $length, $parser->match($input, $offset), (string)$parser);
    }

    public function caseSensitiveData()
    {
        return [
            [new CaseSensitive(true, 'foo'), 'foo', 0, true, 3],
            [new CaseSensitive(true, 'foo'), 'fOO', 0, false, 1],
            [new CaseSensitive(false, 'foo'), 'foo', 0, true, 3],
            [new CaseSensitive(false, 'foo'), 'fOO', 0, true, 3],
            [new CaseSensitive(false, 'FOO'), 'foo', 0, true, 3],
        ];
    }
}
