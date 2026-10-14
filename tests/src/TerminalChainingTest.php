<?php

declare(strict_types=1);

namespace Tests\Src;

use LogicException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;

class TerminalChainingTest extends TestCase
{
    public function testTerminalChainsPreserveTheirPrefixAndNamedGroups(): void
    {
        $base = C::text('a')->named('prefix');
        $grammar = $base->thenSet('01')->thenText('!')->thenEnd();
        $result = $grammar->parse('a1!');
        $this->assertTrue($result->success);
        $this->assertSame('a1!', $result->rawText());
        $this->assertSame('prefix', $result->ast()->firstChild()->name);
        $this->assertSame('a', $result->ast()->firstChild()->rawText());
        $this->assertFalse($grammar->parse('1!')->success);
        $this->assertFalse($grammar->parse('a2!')->success);
        $this->assertFalse($grammar->parse('a1!x')->success);
        $this->assertTrue($base->parse('a')->success);
        $this->assertFalse($base->parse('a1!')->success);
    }

    public function testTerminalOptionsAndUnicodeRemainScopedToTheAppendedTerminal(): void
    {
        $grammar = C::text('(')->thenChar('é')->thenRange('a', 'z')->thenRegex('/[0-9]+/')
            ->thenLiteralWindow(')');
        $this->assertTrue($grammar->parse('(éa12)')->success);
        $this->assertFalse($grammar->parse('(éA12)')->success);
        $this->assertTrue(C::text('a')->thenChar('x', false)->thenSet('z', false)->thenRange('a', 'z', false)->parse('aby1')->success);
        $this->assertFalse(C::text('a')->thenChar('x', false)->parse('ax')->success);
        $this->assertTrue(C::text('a')->thenChar("\xff")->parse(new ByteSource("a\xff"))->success);
    }

    public function testBoundaryAndEmptyTerminalsDoNotReplaceThePrefix(): void
    {
        $grammar = C::nothing()->thenStart()->thenAny()->thenNothing()->thenEnd();
        $this->assertTrue($grammar->parse('é')->success);
        $this->assertFalse($grammar->parse('')->success);
        $this->assertFalse($grammar->parse('éa')->success);
        $this->assertFalse(C::text('a')->thenStart()->parse('a')->success);
    }

    public function testNumericChainsForwardBasesRangesAlphabetsAndLeadingZeros(): void
    {
        $grammar = C::text('n:')->thenBinary(5, 5, true)->thenText('/')
            ->thenOctal(7, 7, true)->thenText('/')
            ->thenHexadecimal(15, 15, true)->thenText('/')
            ->thenOctet(true)->thenText('/')
            ->thenInteger(2, 2, 2, true, 'αβ');
        $this->assertTrue($grammar->parse('n:00101/007/00f/0255/αβα')->success);
        $this->assertFalse($grammar->parse('n:00101/007/00f/0256/αβα')->success);
        $this->assertFalse($grammar->parse('00101/007/00f/0255/αβα')->success);
        $this->assertFalse(C::text('n:')->thenInteger()->parse('n:01')->success);
    }

    public function testStaticRepetitionFactoriesAcceptLiteralsAndRetainBounds(): void
    {
        $this->assertTrue(C::someOf('a')->parse('aa')->success);
        $this->assertFalse(C::someOf('a')->parse('')->success);
        $this->assertTrue(C::manyOf('a')->parse('')->success);
        $this->assertTrue(C::manyOf('a')->parse('aaa')->success);
        $this->assertTrue(C::maybeOf('a')->parse('')->success);
        $this->assertTrue(C::maybeOf('a')->parse('a')->success);
        $this->assertFalse(C::maybeOf('a')->parse('aa')->success);
        $this->assertTrue(C::repeatOf('a', 2, 3)->parse('aaa')->success);
        $this->assertFalse(C::repeatOf('a', 2, 3)->parse('a')->success);
        $this->assertFalse(C::repeatOf('a', 2, 3)->parse('aaaa')->success);
        $this->assertTrue(C::repeatOf(C::nothing(), 0, 0)->parse('')->success);
    }

    public function testStaticRepetitionDetachesParsersAndKeepsReferencesDeferred(): void
    {
        $parser = (new Sequence(new Text('a')))->name('original');
        $grammar = C::someOf($parser);
        $parser->add(new Text('b'))->name('changed');
        $this->assertTrue($grammar->parse('aa')->success);
        $this->assertSame('original', $grammar->parse('a')->ast()->firstChild()->name);
        $rules = new Ruleset();
        $letters = C::someOf($rules->rule('letter'));
        $rules->define('letter', C::text('x'));
        $this->assertTrue($letters->parse('xx')->success);
        $this->assertFalse($letters->parse('xy')->success);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Nullable repetition');
        C::manyOf(C::nothing())->freeze();
    }
}
