<?php

namespace Tests\Fixtures;

use Vanderlee\Comprehend\Extensions\Abnf\Grammar\Rfc5234;

abstract class Rfc5234TestCase extends Rfc4234TestCase
{
    protected static function getRfc()
    {
        return new Rfc5234();
    }
}
