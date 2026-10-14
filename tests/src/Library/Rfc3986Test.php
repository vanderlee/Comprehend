<?php

/** @noinspection PhpUndefinedFieldInspection */

namespace Tests\Src\Library;

use Vanderlee\Comprehend\Source\ByteSource;
use Tests\ParserTestCase;
use Vanderlee\Comprehend\Library\Rfc3986;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * @group library
 * @group rfc
 */
class Rfc3986Test extends ParserTestCase
{
    protected function getRfc()
    {
        return new Rfc3986();
    }
    public function testConstruct()
    {
        $this->assertNotNull($this->getRfc());
    }
    /**
     * @dataProvider rulesData
     *
     * @param Parser $parser
     * @param        $input
     * @param        $match
     * @param        $length
     */
    public function testRules(Parser $parser, $input, $match, $length)
    {
        $this->assertResult($match, $length, $parser->matchPrefix(new ByteSource($input)), (string) $parser);
    }
    public function rulesData()
    {
        /** @var Rfc3986 $uri */
        $uri = $this->getRfc();
        return [
            [$uri->ref('dec_octet'), '0', true, 1],
            [$uri->ref('dec_octet'), '1', true, 1],
            [$uri->ref('dec_octet'), '11', true, 2],
            [$uri->ref('dec_octet'), '255', true, 3],
            [$uri->ref('dec_octet'), '256', true, 2],
            [$uri->ref('IPv4address'), '1.2.3.4', true, 7],
            [$uri->ref('IPv4address'), '255.2.3.4', true, 9],
            [$uri->ref('IPv4address'), '256.2.3.4', false, 2],
            [$uri->ref('IPv4address'), '1.2.3.255', true, 9],
            [$uri->ref('IPv4address'), '1.2.3.256', true, 8],
        ];
    }
}
