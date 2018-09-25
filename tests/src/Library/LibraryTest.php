<?php /** @noinspection PhpUndefinedFieldInspection */

namespace tests\src\library;

use tests\ParserTestCase;
use vanderlee\comprehend\builder\AbstractRuleset;
use vanderlee\comprehend\library\Library;

/**
 * @group library
 */
class LibraryTest extends ParserTestCase
{
    public function testGetInstance()
    {
        $this->assertInstanceOf(Library::class, Library::getInstance());
    }

    public function testMagicGet()
    {
        $this->assertInstanceOf(AbstractRuleset::class, Library::getInstance()->abnf);
    }

    public function testMagicCallStatic()
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertInstanceOf(AbstractRuleset::class, Library::abnf());
    }

    public function testMagicGetUnknown()
    {
        $this->expectExceptionMessage("No ruleset available for `i_do_not_exist`");
        Library::getInstance()->i_do_not_exist;
    }

}