<?php

namespace tests\src\library;

use tests\ParserTestCase;
use vanderlee\comprehend\library\Library;
use vanderlee\comprehend\library\Rfc3986;
use vanderlee\comprehend\parser\Parser;

/**
 * @group library
 * @group rfc
 */
class Rfc3986ParserTest extends ParserTestCase
{

    /**
     * @dataProvider rulesData
     */
    public function testRules(Parser $parser, $input, $match, $length)
    {
        $this->assertResult($match, $length, $parser->match($input), (string)$parser);
    }

    public function rulesData()
    {
        /** @var Rfc3986 $uri */
        $uri = Library::rfc3986();

        return [
            [$uri->dec_octet, '0', true, 1],
            [$uri->dec_octet, '1', true, 1],
            [$uri->dec_octet, '11', true, 2],
            [$uri->dec_octet, '255', true, 3],
            [$uri->dec_octet, '256', true, 2],
            [$uri->IPv4address, '1.2.3.4', true, 7],
            [$uri->IPv4address, '255.2.3.4', true, 9],
            [$uri->IPv4address, '256.2.3.4', false, 2],
            [$uri->IPv4address, '1.2.3.255', true, 9],
            [$uri->IPv4address, '1.2.3.256', true, 8],
            //            [$uri->IPv4address, '11.2.3.4', true, 7],
        ];
    }

}