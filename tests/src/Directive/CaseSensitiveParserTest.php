<?php

namespace tests\src\directive;

use tests\ParserTestCase;
use vanderlee\comprehend\directive\CaseSensitive;

/**
 * @group directive
 */
class CaseSensitiveParserTest extends ParserTestCase
{

    /**
     * @dataProvider caseSensitiveData
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
