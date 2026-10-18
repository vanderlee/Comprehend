<?php

namespace Tests\Fixtures;

use Vanderlee\Comprehend\Extensions\Library\Rfc4234;

abstract class Rfc4234TestCase extends Rfc2234TestCase
{
    protected static function getRfc()
    {
        return new Rfc4234();
    }
}
