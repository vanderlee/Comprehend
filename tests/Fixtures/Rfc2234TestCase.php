<?php

namespace Tests\Fixtures;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Library\Rfc2234;

abstract class Rfc2234TestCase extends ParserTestCase
{
    protected static function getRfc()
    {
        return new Rfc2234();
    }

    public static function rfc2234Data()
    {
        $abnf = static::getRfc();

        return [
            // Core rules
            [$abnf->ref('ALPHA'), 'a', true, 1],
            [$abnf->ref('ALPHA'), 'A', true, 1],
            [$abnf->ref('ALPHA'), 'aa', true, 1],
            [$abnf->ref('ALPHA'), '0', false, 0],
            [$abnf->ref('BIT'), '0', true, 1],
            [$abnf->ref('BIT'), '1', true, 1],
            [$abnf->ref('BIT'), '2', false, 0],
            [$abnf->ref('CHAR'), chr(0x1), true, 1],
            [$abnf->ref('CHAR'), chr(0x7f), true, 1],
            [$abnf->ref('CHAR'), chr(0x0), false, 0],
            [$abnf->ref('DIGIT'), '0', true, 1],
            [$abnf->ref('DIGIT'), '9', true, 1],
            [$abnf->ref('DIGIT'), 'a', false, 0],
            [$abnf->ref('DIGIT'), 'A', false, 0],
            [$abnf->ref('HEXDIG'), '0', true, 1],
            [$abnf->ref('HEXDIG'), '9', true, 1],
            [$abnf->ref('HEXDIG'), 'A', true, 1],
            [$abnf->ref('HEXDIG'), 'F', true, 1],
            [$abnf->ref('HEXDIG'), 'G', false, 0],
            [$abnf->ref('HEXDIG'), 'a', true, 1],
            [$abnf->ref('HEXDIG'), 'f', true, 1],
            [$abnf->ref('HEXDIG'), 'g', false, 0],
            // ABNF rules
            [$abnf->ref('hex_val'), 'x01', true, 3],
            [$abnf->ref('hex_val'), 'x01F', true, 4],
            [$abnf->ref('hex_val'), 'x01G', true, 3],
            [$abnf->ref('hex_val'), 'xG', false, 1],
        ];
    }
}
