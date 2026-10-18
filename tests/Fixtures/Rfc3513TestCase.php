<?php

namespace Tests\Fixtures;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Extensions\Library\Rfc3513;

abstract class Rfc3513TestCase extends ParserTestCase
{
    protected static function getRfc()
    {
        return new Rfc3513();
    }

    public static function rfc3513Data()
    {
        $ipv6 = static::getRfc();

        return [
            // Core rules
            [$ipv6->ref('ipv6_address'), '::1', true, 3],
            [$ipv6->ref('ipv6_address'), '::1/1', true, 3],
            [$ipv6->ref('ipv6_address'), '::0.0.0.1', true, 9],
            [$ipv6->ref('ipv6_address'), '2001:db8::cd30', true, 14],
            [$ipv6->ref('ipv6_address'), 'FF01::101', true, 9],
            [$ipv6->ref('ipv6_address'), '2001:db8::192.0.2.1', true, 19],
            [$ipv6->ref('ipv6_address'), 'FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:FFFF', false, 34],
            [$ipv6->ref('ipv6_address'), 'FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:FFFF', true, 39],
            [$ipv6->ref('ipv6_address'), 'FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:FFFF', true, 39],
            [$ipv6->ref('ipv6'), '::1/1', true, 5],
            [$ipv6->ref('ipv6'), '2001:db8::/32', true, 13],
        ];
    }
}
