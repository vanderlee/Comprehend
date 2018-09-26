<?php

namespace Tests\Src\Parser;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Terminal\Char;

/**
 * @group structure
 * @group parser
 */
class ParserTest extends ParserTestCase
{
    public function testConstructor()
    {
        $this->assertInstanceOf(Parser::class, new Char('a'));
    }

    public function testOffset()
    {
        $parser = new Char('a');

        $this->assertResult(true, 1, $parser('a'));
        $this->assertResult(true, 1, $parser->match('a'));
        $this->assertResult(false, 0, $parser->match('b'));
        $this->expectExceptionMessage("Negative offset");
        $parser->match('a', -1);
    }

    public function testOutput()
    {
        $out = '';

        $parser = new Char('a');
        $parser->callback(function ($text) use (&$out) {
            $out = $text;
        });
        $parser->setResult('foo');
        $parser->token('bar');

        $result = $parser('aa');
        $this->assertResult(true, 1, $result);
        $this->assertEquals('a', $out);
        $this->assertEquals('a', $result->results['foo']);
        $this->assertEquals('bar', $result->token->name);
        $this->assertEquals('a', $result->token->text);
    }


    public function testCharacter()
    {
        $this->assertResult(true, 1, (new Char(ord('a')))->match('aa'));
    }

    public function testCharacterEmpty()
    {
        $this->expectExceptionMessage("Empty argument");
        new Char('');
    }

    public function testCharacterInvalid()
    {
        $this->expectExceptionMessage("Non-character argument");
        new Char('aa');
    }
}
