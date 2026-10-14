<?php

namespace Tests\Src\Parser\Output;

use Vanderlee\Comprehend\Source\ByteSource;
use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Terminal\Text;
/**
 * @group parser
 * @group trait
 */
class CaseSensitiveTraitTest extends ParserTestCase
{
    public function testSetCaseSensitivity()
    {
        $text = new Text('foo');
        $this->assertResult(false, 0, $text->matchPrefix(new ByteSource('FOO')));
        $text->caseInsensitive();
        $this->assertResult(true, 3, $text->matchPrefix(new ByteSource('FOO')));
        $text->caseSensitive();
        $this->assertResult(false, 0, $text->matchPrefix(new ByteSource('FOO')));
        $text->setCaseSensitivity(false);
        $this->assertResult(true, 3, $text->matchPrefix(new ByteSource('FOO')));
        $text->setCaseSensitivity(true);
        $this->assertResult(false, 0, $text->matchPrefix(new ByteSource('FOO')));
    }
}
