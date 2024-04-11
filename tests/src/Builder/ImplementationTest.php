<?php

namespace Tests\Src\Builder;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Terminal\Text;

/**
 * @group structure
 * @group parser
 */
class ImplementationTest extends ParserTestCase
{
    public function testNoParserDefined()
    {
        $definition = new Definition();
        $this->expectExceptionMessage('Parser not defined');
        $definition()->match('12');
    }

    public function testNoParserDefinedString()
    {
        $definition = new Definition();
        $this->assertEquals('', (string)$definition());
    }

    public function testMagicGetParserDefined()
    {
        $definition = new Definition(new Text('foo'));
        $implementation = $definition->build();
        $this->assertInstanceOf(Parser::class, $implementation->parser);
    }

    public function testMagicGetParserUndefined()
    {
        $definition = new Definition();
        $implementation = $definition->build();
        $this->assertNull($implementation->parser);
    }

    public function testMagicGetUnknownProperty()
    {
        $definition = new Definition();
        $implementation = $definition->build();
        $this->expectExceptionMessage('Property `i_do_not_exist` does not exist');
        $this->assertNull($implementation->i_do_not_exist);
    }

    public function testMagicGetParserFromGenerator()
    {
        $definition = new Definition(function () {
            return new Text('foo');
        });
        $implementation = $definition->build();
        $this->assertInstanceOf(Parser::class, $implementation->parser);
    }
}
