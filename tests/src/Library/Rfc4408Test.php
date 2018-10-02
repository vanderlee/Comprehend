<?php /** @noinspection PhpUndefinedFieldInspection */

namespace Tests\Src\Library;

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
        $this->assertResult($match, $length, $parser->match($input), (string) $parser . ' <=> ' . $input);
    }

    public function rulesData()
    {
        $spf = $this->getRfc();

        return [
            // Core rules
            [$spf->qnum, '0', true, 1],
            [$spf->qnum, '255', true, 3],
            [$spf->qnum, '2555', true, 3],
            [$spf->qnum, '255.0', true, 3],
            [$spf->qnum, '256', true, 2],
            [$spf->qnum, '12.3', true, 2],
            [$spf->ip4_cidr_length, '/', false, 1],
            [$spf->ip4_cidr_length, '/1', true, 2],
            [$spf->ip4_cidr_length, '/12', true, 3],
            [$spf->ip4_cidr_length, '/123', true, 4],
            [new Sequence($spf->qnum, '.', $spf->qnum), '12.45', true, 5],
            [$spf->ip4_network, '123.45.67.89', true, 12],
            [$spf->IP4, 'ip4:123.45.67.89', true, 16],
            [$spf->IP4, 'ip4:123.45.67', false, 13],
            [$spf->IP4, 'ip4:123.45.67.890', true, 16],
            [$spf->IP4, 'ip4:123.123.1234.123', false, 15],
            [$spf->IP4, 'ip4:123.45.67.89/0', true, 18],
            [$spf->IP4, 'ip4:123.45.67.89/32', true, 19],
            [$spf->IP4, 'ip4:123.45.67.89/33', true, 19],   // @todo valid according to BNF but invalid IPv4 address!
        ];
    }

}