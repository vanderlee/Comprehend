<?php

/** @noinspection PhpUndefinedFieldInspection */
namespace Tests\Src\Library;

use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;
use Tests\ParserTestCase;
use Vanderlee\Comprehend\Library\Rfc4408;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
/**
 * @group library
 * @group rfc
 */
class Rfc4408Test extends ParserTestCase
{
    protected function getRfc()
    {
        return new Rfc4408();
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
        $this->assertResult($match, $length, $parser->matchPrefix(new ByteSource($input)), (string) $parser . ' <=> ' . $input);
    }
    public function rulesData()
    {
        $spf = $this->getRfc();
        return [
            // Core rules
            [$spf->rule('qnum'), '0', true, 1],
            [$spf->rule('qnum'), '255', true, 3],
            [$spf->rule('qnum'), '2555', true, 3],
            [$spf->rule('qnum'), '255.0', true, 3],
            [$spf->rule('qnum'), '256', true, 2],
            [$spf->rule('qnum'), '12.3', true, 2],
            [$spf->rule('ip4_cidr_length'), '/', false, 1],
            [$spf->rule('ip4_cidr_length'), '/1', true, 2],
            [$spf->rule('ip4_cidr_length'), '/12', true, 3],
            [$spf->rule('ip4_cidr_length'), '/123', true, 4],
            [new Sequence($spf->rule('qnum'), new Text('.'), $spf->rule('qnum')), '12.45', true, 5],
            [$spf->rule('ip4_network'), '123.45.67.89', true, 12],
            [$spf->rule('IP4'), 'ip4:123.45.67.89', true, 16],
            [$spf->rule('IP4'), 'ip4:123.45.67', false, 13],
            [$spf->rule('IP4'), 'ip4:123.45.67.890', true, 16],
            [$spf->rule('IP4'), 'ip4:123.123.1234.123', false, 15],
            [$spf->rule('IP4'), 'ip4:123.45.67.89/0', true, 18],
            [$spf->rule('IP4'), 'ip4:123.45.67.89/32', true, 19],
            [$spf->rule('IP4'), 'ip4:123.45.67.89/33', true, 19],
        ];
    }
}
