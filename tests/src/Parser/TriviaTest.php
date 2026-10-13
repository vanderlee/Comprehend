<?php

declare(strict_types=1);

namespace Tests\Src\Parser;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Compatibility\LegacyParserAdapter;
use Vanderlee\Comprehend\Directive\Space;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Regex;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\Utf8Source;

class TriviaTest extends ParserTestCase
{
    public function testExplicitAndAutomaticTriviaRoundTripNestedUnnamedInput()
    {
        $trivia = (new Regex('~(?:\s|/\*[^*]*\*/)*~'))->lexeme()->trivia();
        $body = (new Sequence('(', new Sequence('a', 'b'), ')'))->spacing($trivia);
        $parser = (new Sequence($trivia, $body, $trivia))->spacing(null);
        $text = " \n( /*first*/ a\tb /*last*/ ) \n";
        $result = $parser->parseAll(new Utf8Source($text));
        $this->assertTrue($result->success);
        $this->assertSame($text, $result->node->rawText());
        $children = $result->node->children;
        $this->assertTrue($children[0]->isTrivia);
        $this->assertTrue($children[2]->isTrivia);
        $this->assertSame($text, implode('', array_map(fn ($node) => $node->rawText(), $children)));
        $bodyChildren = $children[1]->children;
        $this->assertSame(['(', ' /*first*/ ', "a\tb", ' /*last*/ ', ')'], array_map(fn ($node) => $node->rawText(), $bodyChildren));
        $this->assertTrue($bodyChildren[1]->isTrivia);
        $this->assertTrue($bodyChildren[2]->children[1]->isTrivia);
        $this->assertSame("\t", $bodyChildren[2]->children[1]->rawText());
    }

    public function testZeroWidthTriviaRetainsActualPosition()
    {
        $trivia = Repeat::star(' ')->trivia();
        $result = (new Sequence($trivia, 'a', $trivia))->parseAll(new Utf8Source('a'));
        $this->assertTrue($result->success);
        $this->assertSame([0, 0], [$result->node->children[0]->span->start, $result->node->children[0]->span->end]);
        $this->assertSame([1, 1], [$result->node->children[2]->span->start, $result->node->children[2]->span->end]);
        $this->assertTrue($result->node->children[0]->isTrivia);
    }

    public function testUtf8TriviaHasWholeCodePointByteSpans()
    {
        $trivia = Repeat::star(new Text("\u{00a0}"))->trivia();
        $result = (new Sequence($trivia, new Text('é'), $trivia))->parseAll(new Utf8Source("\u{00a0}é\u{00a0}"));
        $this->assertTrue($result->success);
        $this->assertSame([0, 2], [$result->node->children[0]->span->start, $result->node->children[0]->span->end]);
        $this->assertSame([4, 6], [$result->node->children[2]->span->start, $result->node->children[2]->span->end]);
        $this->assertSame("\u{00a0}", $result->node->children[2]->rawText());
    }

    public function testLexemeDisablesNestedOverridesAndRestoresOuterSpacing()
    {
        $literal = (new Sequence('a', 'b'))->spacing(' ');
        $parser = (new Sequence($literal->lexeme(), 'c'))->spacing(' ');
        $this->assertTrue($parser->parseAll(new Utf8Source('ab c'))->success);
        $this->assertFalse($parser->parseAll(new Utf8Source('a b c'))->success);
        $this->assertTrue($literal->parseAll(new Utf8Source('a b'))->success);
        $this->assertTrue($parser->match('ab c')->match);
        $this->assertFalse($parser->match('a b c')->match);
    }

    public function testLexemeScopeRestoresAfterFailedAlternative()
    {
        $choice = new Choice((new Sequence('a', 'x'))->lexeme(), new Sequence('a', 'b'));
        $parser = new Space(' ', $choice);
        $this->assertTrue($parser->parseAll(new Utf8Source('a b'))->success);
    }

    public function testExplicitLiteralSpacesStillMatchInsideLexeme()
    {
        $parser = (new Sequence('a', ' ', 'b'))->spacing(' ')->lexeme();
        $this->assertTrue($parser->parseAll(new Utf8Source('a b'))->success);
        $this->assertFalse($parser->parseAll(new Utf8Source('ab'))->success);
    }

    public function testRepeatRetainsOnlyAcceptedInterveningTrivia()
    {
        $calls = 0;
        $spacer = (new Text(' '))->callback(function () use (&$calls) { ++$calls; });
        $parser = Repeat::plus('a')->spacing($spacer);
        $result = $parser->matchPrefix(new Utf8Source('a a '));
        $this->assertSame('a a', $result->node->rawText());
        $this->assertCount(3, $result->node->children);
        $this->assertTrue($result->node->children[1]->isTrivia);
        $this->assertSame(1, $calls);
        $calls = 0;
        $this->assertFalse($parser->parseAll(new Utf8Source('a a '))->success);
        $this->assertSame(0, $calls);
    }

    public function testLegacySpacingDoesNotAcquireLeadingOrTrailingSkip()
    {
        $parser = (new Sequence('a', 'b'))->spacing(' ');
        $adapter = new LegacyParserAdapter($parser);
        foreach (['a b' => true, ' a b' => false, 'a b ' => false, 'ab' => false] as $text => $success) {
            $this->assertSame($success, $parser->parseAll(new Utf8Source($text))->success);
            $this->assertSame($success, $adapter->parseAll($text)->match);
        }
        $this->assertTrue($parser->match('a b ')->match);
    }

    public function testAutomaticTriviaDoesNotAddNamedResultsOrTokenChildren()
    {
        $spacer = (new Text(' '))->token('space')->setResult('space');
        $parser = (new Sequence((new Text('a'))->token('a'), (new Text('b'))->token('b')))
            ->token('root')->spacing($spacer);
        $result = $parser->parseAll(new Utf8Source('a b'));
        $this->assertSame([], $result->results());
        $this->assertCount(2, $result->token()->children);
        $this->assertCount(3, $result->node->children);
        $this->assertTrue($result->node->children[1]->isTrivia);
    }
}
