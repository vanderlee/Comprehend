<?php

namespace Tests\Fixtures;

use Vanderlee\Comprehend\Extensions\Library\Rfc5234;

abstract class Rfc5234TestCase extends Rfc4234TestCase
{
    protected static function getRfc()
    {
        return new Rfc5234();
    }
}
