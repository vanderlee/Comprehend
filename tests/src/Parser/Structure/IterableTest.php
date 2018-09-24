<?php

namespace tests\src\parser\structure;

use tests\ParserTestCase;
use vanderlee\comprehend\parser\structure\All;
use vanderlee\comprehend\parser\structure\Repeat;
use vanderlee\comprehend\parser\structure\Sequence;
use vanderlee\comprehend\parser\terminal\Char;

/**
 * @group structure
 * @group parser
 */
class IterableTest extends ParserTestCase
{
    public function testIteratorAggregate() {
        $seq = new Sequence('a', 'b', 'c');

        $this->assertCount(3, $seq);
        foreach ($seq as $parser) {
            $this->assertInstanceOf(Char::class, $parser);
        }
    }

    public function testArrayAccess() {
        $seq = new Sequence('a', 'b', 'c');

        $this->assertEquals("'b'", $seq[1]);
        $this->assertResult(true, 3, $seq->match('abc'));

        $seq[1] = new Char('x');
        $this->assertEquals("'x'", $seq[1]);
        $this->assertResult(true, 3, $seq->match('axc'));

        unset($seq[1]);
        $this->assertEquals(null, $seq[1]);
        $this->assertResult(true, 2, $seq->match('ac'));

        $this->assertFalse(isset($seq[1]));

        $seq[] = new Char('d');
        $this->assertResult(true, 3, $seq->match('acd'));
    }
}
