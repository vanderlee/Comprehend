<?php

namespace tests\src\parser;

use tests\ParserTestCase;
use vanderlee\comprehend\parser\Stub;

/**
 * @group structure
 * @group parser
 */
class StubParserTest extends ParserTestCase
{

    public function testConstruct()
    {
        $parser = new Stub;
        $this->expectExceptionMessage('Missing parser');
        $parser->match('foo');
    }

    public function testSetParser()
    {
        $parser         = new Stub;
        $parser->parser = 'foo';
        $this->assertResult(true, 3, $parser->match('foo'));
    }

    public function testSetOther()
    {
        $parser = new Stub;
        $this->expectExceptionMessage('Property `foo` does not exist');
        $parser->foo = 'foo';
    }

    public function testToString()
    {
        $parser         = new Stub;
        $parser->parser = 'foo';
        $this->assertEquals('"foo"', (string)$parser);
    }

}
