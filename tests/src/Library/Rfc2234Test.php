<?php

/** @noinspection PhpUndefinedFieldInspection */
namespace Tests\Src\Library;

use Vanderlee\Comprehend\Source\ByteSource;
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
        $this->assertResult($match, $length, $parser->matchPrefix(new ByteSource($input)), (string) $parser);
    }
    public function rfc2234Data()
    {
        $abnf = $this->getRfc();
        return [
            // Core rules
            [$abnf->rule('ALPHA'), 'a', true, 1],
            [$abnf->rule('ALPHA'), 'A', true, 1],
            [$abnf->rule('ALPHA'), 'aa', true, 1],
            [$abnf->rule('ALPHA'), '0', false, 0],
            [$abnf->rule('BIT'), '0', true, 1],
            [$abnf->rule('BIT'), '1', true, 1],
            [$abnf->rule('BIT'), '2', false, 0],
            [$abnf->rule('CHAR'), chr(0x1), true, 1],
            [$abnf->rule('CHAR'), chr(0x7f), true, 1],
            [$abnf->rule('CHAR'), chr(0x0), false, 0],
            [$abnf->rule('DIGIT'), '0', true, 1],
            [$abnf->rule('DIGIT'), '9', true, 1],
            [$abnf->rule('DIGIT'), 'a', false, 0],
            [$abnf->rule('DIGIT'), 'A', false, 0],
            [$abnf->rule('HEXDIG'), '0', true, 1],
            [$abnf->rule('HEXDIG'), '9', true, 1],
            [$abnf->rule('HEXDIG'), 'A', true, 1],
            [$abnf->rule('HEXDIG'), 'F', true, 1],
            [$abnf->rule('HEXDIG'), 'G', false, 0],
            [$abnf->rule('HEXDIG'), 'a', false, 0],
            // ABNF rules
            [$abnf->rule('hex_val'), 'x01', true, 3],
            [$abnf->rule('hex_val'), 'x01F', true, 4],
            [$abnf->rule('hex_val'), 'x01G', true, 3],
            [$abnf->rule('hex_val'), 'xG', false, 1],
        ];
    }
}
