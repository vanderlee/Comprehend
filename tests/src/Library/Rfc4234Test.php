<?php

/** @noinspection PhpUndefinedFieldInspection */

namespace Tests\Src\Library;

require_once 'Rfc2234Test.php';

use Vanderlee\Comprehend\Library\Rfc4234;

/**
 * @group library
 * @group rfc
 */
class Rfc4234Test extends Rfc2234Test
{
    public function testConstruct()
    {
        $this->assertNotNull($this->getRfc());
    }

    protected function getRfc()
    {
        return new Rfc4234();
    }
}
