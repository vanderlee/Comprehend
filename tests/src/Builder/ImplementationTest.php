<?php

namespace tests\src\builder;

use tests\ParserTestCase;
use vanderlee\comprehend\builder\Definition;

/**
 * @group structure
 * @group parser
 */
class ImplementationTest extends ParserTestCase
{
    public function testNoParserDefined()
    {
        $definition = new Definition();
        $this->expectExceptionMessage("Parser not defined");
        $definition()->match('12');
    }

    public function testNoParserDefinedString()
    {
        $definition = new Definition();
        $this->assertEquals('', (string)$definition());
    }
}
