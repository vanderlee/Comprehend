<?php

namespace Tests\Fixtures;

use Vanderlee\Comprehend\Extensions\Abnf\Grammar\Rfc7405;

abstract class Rfc7405TestCase extends Rfc5234TestCase
{
    protected static function getRfc()
    {
        return new Rfc7405();
    }

    public static function rfc7405Data()
    {
        $abnf = static::getRfc();

        return [
            [$abnf->ref('case_sensitive_string'), '%s"SenSiTive"', true, 13],
            [$abnf->ref('case_sensitive_string'), '%s""', true, 4],
            [$abnf->ref('case_sensitive_string'), '%s', false, 2],
            [$abnf->ref('case_sensitive_string'), '%sSenSiTive', false, 2],
            [$abnf->ref('case_sensitive_string'), '"SenSiTive"', false, 0],
            [$abnf->ref('case_insensitive_string'), '%i"SenSiTive"', true, 13],
            [$abnf->ref('case_insensitive_string'), '%i""', true, 4],
            [$abnf->ref('case_insensitive_string'), '%i', false, 2],
            [$abnf->ref('case_insensitive_string'), '%iSenSiTive', false, 2],
            [$abnf->ref('case_insensitive_string'), '"SenSiTive"', true, 11],
            [$abnf->ref('case_insensitive_string'), 'SenSiTive', false, 0],
        ];
    }
}
