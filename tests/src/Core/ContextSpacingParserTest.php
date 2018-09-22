<?php

namespace tests\src\core;

use tests\ParserTestCase;
use vanderlee\comprehend\parser\structure\Repeat;
use vanderlee\comprehend\parser\structure\Sequence;
use vanderlee\comprehend\parser\terminal\Char;

/**
 * @group directive
 */
class ContextSpacingParserTest extends ParserTestCase
{

    public function testSequenceLexeme()
    {
        $spacer = new Repeat('-', 0);
        $parser = new Sequence('foo', 'bar');
        $parser->spacing($spacer);

        $this->assertResult(true, 6, $parser->match('foobar'));
        $this->assertResult(true, 7, $parser->match('foo-bar'));
        $this->assertResult(true, 8, $parser->match('foo--bar'));
    }

    public function testSequenceLexemeRequired()
    {
        $spacer = new Repeat('-', 1);
        $parser = new Sequence('foo', 'bar');
        $parser->spacing($spacer);

        $this->assertResult(false, 3, $parser->match('foobar'));
        $this->assertResult(true, 7, $parser->match('foo-bar'));
        $this->assertResult(true, 8, $parser->match('foo--bar'));
    }

    public function testRepeatLexeme()
    {
        $spacer = new Repeat('-', 0);
        $parser = new Repeat('foo');
        $parser->spacing($spacer);

        $this->assertResult(true, 11, $parser->match('foo-foo-foo'));
        $this->assertResult(true, 10, $parser->match('foo-foofoo'));
        $this->assertResult(true, 9, $parser->match('foofoofoo'));
    }

    public function testRepeatLexemeRequired()
    {
        $spacer = new Repeat('-', 1);
        $parser = new Repeat('foo');
        $parser->spacing($spacer);

        $this->assertResult(true, 11, $parser->match('foo-foo-foo'));
        $this->assertResult(true, 7, $parser->match('foo-foofoo'));
        $this->assertResult(true, 3, $parser->match('foofoofoo'));
    }

    public function testOutsideSpacing()
    {
        $spacer = new Char(' ');
        $parser = new Sequence('foo', 'bar');
        $parser->spacing($spacer);

        $this->assertResult(true, 7, $parser->match('foo bar'));
        $this->assertResult(false, 0, $parser->match(' foo bar'));
        $this->assertResult(true, 7, $parser->match('foo bar '));
    }

    public function testStackSpacing()
    {
        $spacer = new Repeat('-', 0, 1);
        $inner  = new Sequence('b', 'a', 'r');
        $outer  = new Sequence('foo', $inner, 'baz');
        $outer->spacing($spacer);

        $this->assertResult(true, 11, $outer->match('foo-bar-baz'));
        $this->assertResult(true, 13, $outer->match('foo-b-a-r-baz'));
        $this->assertResult(false, 5, $outer->match('foo-b.a.r-baz'));

        $inner->spacing(false);
        $this->assertResult(true, 11, $outer->match('foo-bar-baz'));
        $this->assertResult(false, 5, $outer->match('foo-b-a-r-baz'));
        $this->assertResult(false, 5, $outer->match('foo-b.a.r-baz'));

        $inner->spacing(true);
        $this->assertResult(true, 11, $outer->match('foo-bar-baz'));
        $this->assertResult(true, 13, $outer->match('foo-b-a-r-baz'));
        $this->assertResult(false, 5, $outer->match('foo-b.a.r-baz'));

        $inner->spacing(new Repeat('.', 0, 1));
        $this->assertResult(true, 11, $outer->match('foo-bar-baz'));
        $this->assertResult(false, 5, $outer->match('foo-b-a-r-baz'));
        $this->assertResult(true, 13, $outer->match('foo-b.a.r-baz'));
    }

}
