<?php

namespace Tests\Fixtures;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Extensions\Library\Rfc3986;

abstract class Rfc3986TestCase extends ParserTestCase
{
    protected static function getRfc()
    {
        return new Rfc3986();
    }

    public static function completeReferences(): array
    {
        return [
            ['URI', 'https://example.com/path?query#fragment'],
            ['URI', 'urn:example:animal'],
            ['URI', 'file:/path'],
            ['URI', 'scheme:'],
            ['relative_ref', '//example.com/path'],
            ['relative_ref', '/path'],
            ['relative_ref', 'path/to/file'],
            ['relative_ref', '?query'],
            ['relative_ref', '#fragment'],
            ['relative_ref', ''],
        ];
    }

    public static function rulesData()
    {
        /** @var Rfc3986 $uri */
        $uri = static::getRfc();

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
