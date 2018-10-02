<?php

/** @noinspection PhpUndefinedFieldInspection */

namespace Tests\Src\Library;

require_once 'Rfc5234Test.php';

use Vanderlee\Comprehend\Library\Rfc7405;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * @group library
 * @group rfc
 */
class Rfc7405Test extends Rfc5234Test
{
    protected function getRfc()
    {
        return new Rfc7405();
    }

    /**
     * @dataProvider rfc7405Data
     *
     * @param Parser $parser
     * @param        $input
     * @param        $match
     * @param        $length
     */
    public function testRfc7405(Parser $parser, $input, $match, $length)
    {
        $this->assertResult($match, $length, $parser->match($input), (string) $parser);
    }

    public function rfc7405Data()
    {
        $abnf = $this->getRfc();

        return [
            [$abnf->case_sensitive_string, '%s"SenSiTive"', true, 13],
            [$abnf->case_sensitive_string, '%s""', true, 4],
            [$abnf->case_sensitive_string, '%s', false, 2],
            [$abnf->case_sensitive_string, '%sSenSiTive', false, 2],
            [$abnf->case_sensitive_string, '"SenSiTive"', false, 0],

            [$abnf->case_insensitive_string, '%i"SenSiTive"', true, 13],
            [$abnf->case_insensitive_string, '%i""', true, 4],
            [$abnf->case_insensitive_string, '%i', false, 2],
            [$abnf->case_insensitive_string, '%iSenSiTive', false, 2],
            [$abnf->case_insensitive_string, '"SenSiTive"', true, 11],
            [$abnf->case_insensitive_string, 'SenSiTive', false, 0],
        ];
    }
}
