<?php

/** @noinspection PhpUndefinedFieldInspection */

namespace Tests\Src\Library;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Library\Rfc3513;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * @group library
 * @group rfc
 */
class Rfc3513Test extends ParserTestCase
{
    protected function getRfc()
    {
        return new Rfc3513();
    }

    public function testConstruct()
    {
        $this->assertNotNull($this->getRfc());
    }

    /**
     * @dataProvider rfc3513Data
     *
     * @param Parser $parser
     * @param        $input
     * @param        $match
     * @param        $length
     */
    public function testRfc3513(Parser $parser, $input, $match, $length)
    {
        $this->assertResult($match, $length, $parser->match($input), "{$parser} == `{$input}`");
    }

    public function rfc3513Data()
    {
        $ipv6 = $this->getRfc();

        return [
            // Core rules
            [$ipv6->ipv6_address, '::1', true, 3],
            [$ipv6->ipv6_address, '::1/1', true, 3],
            [$ipv6->ipv6_address, '::0.0.0.1', true, 9],
            [$ipv6->ipv6_address, 'FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:FFFF', false, 34],
            [$ipv6->ipv6_address, 'FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:FFFF', true, 39],
            [$ipv6->ipv6_address, 'FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:FFFF', true, 39],
            [$ipv6->ipv6, '::1/1', true, 5],
        ];
    }
}
