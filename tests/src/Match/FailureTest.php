<?php

namespace Tests\Src\Match;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Match\Failure;

/**
 * @group match
 */
class FailureTest extends ParserTestCase
{

    public function testZero()
    {
        $failure = new Failure;

        $this->assertEquals(0, $failure->length);
        $this->assertFalse($failure->match);
        $this->assertEquals('Failed match at 0 characters', (string)$failure);
    }

    public function testMagicGet()
    {
        $failure = new Failure(3);

        $this->assertEquals($failure, $failure->resolve());
        $this->assertEquals(3, $failure->length);
        $this->assertEquals([], $failure->results);
        $this->assertEquals(null, $failure->result);
        $this->assertEquals(null, $failure->token);
        $this->assertEquals(false, $failure->hasResult('foo'));
        $this->assertEquals('def', $failure->getResult('foo', 'def'));
        $this->assertFalse($failure->match);
        $this->assertEquals('Failed match at 3 characters', (string)$failure);

        $this->expectExceptionMessage("Property name `i_do_not_exist` not recognized");
        /** @noinspection PhpUndefinedFieldInspection */
        $failure->i_do_not_exist;
    }

}
