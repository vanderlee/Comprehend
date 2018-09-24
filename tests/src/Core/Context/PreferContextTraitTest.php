<?php

namespace tests\src\core;

use tests\ParserTestCase;
use vanderlee\comprehend\core\Context;

/**
 * @group core
 */
class PreferContextTraitTest extends ParserTestCase
{

    public function testUnsupportedType()
    {
        $this->expectExceptionMessage("Invalid preference `bad preference`");
        new Context(null, true, 'bad preference');
    }
}
