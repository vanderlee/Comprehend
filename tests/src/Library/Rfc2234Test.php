<?php

/** @noinspection PhpUndefinedFieldInspection */

namespace Tests\Src\Library;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Library\Rfc2234;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * @group library
 * @group rfc
 */
class Rfc2234Test extends ParserTestCase
{
    protected function getRfc()
    {
        return new Rfc2234();
    }

    public function testConstruct()
    {
        $this->assertNotNull($this->getRfc());
    }

    /**
     * @dataProvider rfc2234Data
     *
     * @param Parser $parser
     * @param        $input
     * @param        $match
     * @param        $length
     */
    public function testRfc2234(Parser $parser, $input, $match, $length)
    {
        $this->assertResult($match, $length, $parser->match($input), (string) $parser);
    }

    public function rfc2234Data()
    {
        $abnf = $this->getRfc();

        return [
            // Core rules
            [$abnf->ALPHA, 'a', true, 1],
            [$abnf->ALPHA, 'A', true, 1],
            [$abnf->ALPHA, 'aa', true, 1],
            [$abnf->ALPHA, '0', false, 0],

            [$abnf->BIT, '0', true, 1],
            [$abnf->BIT, '1', true, 1],
            [$abnf->BIT, '2', false, 0],

            [$abnf->CHAR, chr(0x01), true, 1],
            [$abnf->CHAR, chr(0x7F), true, 1],
            [$abnf->CHAR, chr(0x00), false, 0],

            [$abnf->DIGIT, '0', true, 1],
            [$abnf->DIGIT, '9', true, 1],
            [$abnf->DIGIT, 'a', false, 0],
            [$abnf->DIGIT, 'A', false, 0],

            [$abnf->HEXDIG, '0', true, 1],
            [$abnf->HEXDIG, '9', true, 1],
            [$abnf->HEXDIG, 'A', true, 1],
            [$abnf->HEXDIG, 'F', true, 1],
            [$abnf->HEXDIG, 'G', false, 0],
            [$abnf->HEXDIG, 'a', false, 0],

            // ABNF rules
            [$abnf->hex_val, 'x01', true, 3],
            [$abnf->hex_val, 'x01F', true, 4],
            [$abnf->hex_val, 'x01G', true, 3],
            [$abnf->hex_val, 'xG', false, 1],
        ];
    }
}
