<?php /** @noinspection PhpUndefinedFieldInspection */

namespace Tests\Src\Library;

require_once 'Rfc4234Test.php';

use Vanderlee\Comprehend\Library\Rfc5234;

/**
 * @group library
 * @group rfc
 */
class Rfc5234Test extends Rfc4234Test
{
    public function testConstruct()
    {
        $this->assertNotNull($this->getRfc());
    }

    protected function getRfc()
    {
        return new Rfc5234();
    }
}