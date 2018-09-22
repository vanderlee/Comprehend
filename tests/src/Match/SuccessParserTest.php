<?php

namespace tests\src\match;

use tests\ParserTestCase;
use vanderlee\comprehend\match\Success;

/**
 * @group match
 */
class SuccessParserTest extends ParserTestCase
{

    public function testZero()
    {
        $Success = new Success;

        $this->assertEquals(0, $Success->length);
        $this->assertTrue($Success->match);
        $this->assertEquals('Successfully matched 0 characters', (string)$Success);
    }

    public function testLength()
    {
        $Success = new Success(3);

        $this->assertEquals(3, $Success->length);
        $this->assertTrue($Success->match);
        $this->assertEquals('Successfully matched 3 characters', (string)$Success);
    }

}
