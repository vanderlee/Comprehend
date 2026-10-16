<?php

namespace Tests\Fixtures;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Library\Rfc4408;
use Vanderlee\Comprehend\Parser\Structure\Sequence;

abstract class Rfc4408TestCase extends ParserTestCase
{
    protected static function getRfc()
    {
        return new Rfc4408();
    }

    public static function completeRecords(): array
    {
        return [
            ['v=spf1', true],
            ['v=spf1 -all', true],
            ['v=spf1 ip4:192.0.2.1 -all  ', true],
            ['v=spf1 a mx -all', true],
            ['v=spf1 ip6:2001:db8::/32 -all', true],
            ['v=spf2 -all', false],
            ['v=spf1 invalid', false],
        ];
    }

    public static function rulesData()
    {
        $spf = static::getRfc();

        return [
            // Core rules
            [$spf->ref('qnum'), '0', true, 1],
            [$spf->ref('qnum'), '255', true, 3],
            [$spf->ref('qnum'), '2555', true, 3],
            [$spf->ref('qnum'), '255.0', true, 3],
            [$spf->ref('qnum'), '256', true, 2],
            [$spf->ref('qnum'), '12.3', true, 2],
            [$spf->ref('ip4_cidr_length'), '/', false, 1],
            [$spf->ref('ip4_cidr_length'), '/1', true, 2],
            [$spf->ref('ip4_cidr_length'), '/12', true, 3],
            [$spf->ref('ip4_cidr_length'), '/123', true, 4],
            [new Sequence($spf->ref('qnum'), new Text('.'), $spf->ref('qnum')), '12.45', true, 5],
            [$spf->ref('ip4_network'), '123.45.67.89', true, 12],
            [$spf->ref('IP4'), 'ip4:123.45.67.89', true, 16],
            [$spf->ref('IP4'), 'ip4:123.45.67', false, 13],
            [$spf->ref('IP4'), 'ip4:123.45.67.890', true, 16],
            [$spf->ref('IP4'), 'ip4:123.123.1234.123', false, 15],
            [$spf->ref('IP4'), 'ip4:123.45.67.89/0', true, 18],
            [$spf->ref('IP4'), 'ip4:123.45.67.89/32', true, 19],
            [$spf->ref('IP4'), 'ip4:123.45.67.89/33', true, 19],
        ];
    }
}
