<?php

namespace Tests\Src\Core;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Core\Context;

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
