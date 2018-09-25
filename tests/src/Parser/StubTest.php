<?php

namespace tests\src\parser;

use tests\ParserTestCase;
use vanderlee\comprehend\parser\Stub;
use vanderlee\comprehend\parser\terminal\Text;

/**
 * @group structure
 * @group parser
 */
class StubTest extends ParserTestCase
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

    public function testSetParserFailure()
    {
        $parser         = new Stub;
        $parser->parser = 'foo';
        $this->assertResult(false, 0, $parser->match('bar'));
    }

    public function testSetMissingProperty()
    {
        $stub = new Stub();
        $this->expectExceptionMessage("Property `i_do_not_exist` does not exist");
        /** @noinspection PhpUndefinedFieldInspection */
        $stub->i_do_not_exist = 'foo';
    }

    public function testGetParser()
    {
        $parser         = new Stub;
        $parser->parser = 'foo';
        $this->assertInstanceOf(Text::class, $parser->parser);
    }

    public function testGetMissingProperty()
    {
        $stub = new Stub();
        $this->expectExceptionMessage("Property `i_do_not_exist` does not exist");
        /** @noinspection PhpUndefinedFieldInspection */
        echo $stub->i_do_not_exist;
    }

    public function testToString()
    {
        $parser         = new Stub;
        $parser->parser = 'foo';
        $this->assertEquals('"foo"', (string)$parser);
    }

    public function testToStringMissingParser()
    {
        $parser = new Stub;
        /** @noinspection HtmlUnknownTag */
        $this->assertEquals('<undefined>', (string)$parser);
    }

}
