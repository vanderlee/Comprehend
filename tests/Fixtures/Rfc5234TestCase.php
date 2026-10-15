<?php

namespace Tests\Fixtures;

use Vanderlee\Comprehend\Library\Rfc5234;

abstract class Rfc5234TestCase extends Rfc4234TestCase
{
    protected static function getRfc()
    {
        return new Rfc5234();
    }
}
