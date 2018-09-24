<?php

namespace tests\src\match;

use tests\ParserTestCase;
use vanderlee\comprehend\match\Success;

/**
 * @group match
 */
class SuccessTest extends ParserTestCase
{

    public function testZero()
    {
        $Success = new Success;

        $this->assertEquals(0, $Success->length);
        $this->assertTrue($Success->match);
        $this->assertEquals('Successfully matched 0 characters', (string)$Success);
    }

    /**
     * @throws \ErrorException
     */
    public function testMagicGet()
    {
        $success = new Success(3);

        $this->assertEquals($success, $success->resolve());
        $this->assertEquals(3, $success->length);
        $this->assertEquals([], $success->results);
        $this->assertEquals(null, $success->result);
        $this->assertEquals(null, $success->token);
        $this->assertEquals(false, $success->hasResult('foo'));
        $this->assertEquals('def', $success->getResult('foo', 'def'));
        $this->assertTrue($success->match);
        $this->assertEquals('Successfully matched 3 characters', (string)$success);

        $this->expectExceptionMessage("Property name `i_do_not_exist` not recognized");
        $success->i_do_not_exist;
    }

    /**
     * @throws \ErrorException
     */
    public function testRepeatResolve()
    {
        $success = new Success(3);

        $success->resolve();
        $this->expectExceptionMessage("Match already resolved");
        $success->resolve();
    }
}
