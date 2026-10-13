<?php

declare(strict_types=1);

namespace Tests\Src\Parser;

use InvalidArgumentException;
use Tests\ParserTestCase;
use Vanderlee\Comprehend\Compatibility\CoordinatePolicy;
use Vanderlee\Comprehend\Compatibility\LegacyParserAdapter;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;

class ConsumptionTest extends ParserTestCase
{
    /** @dataProvider consumptionData */
    public function testPrefixAndFullInputHaveExplicitContracts($parser, $source, $offset, $prefixSuccess, $length, $allSuccess)
    {
        $prefix = $parser->matchPrefix($source, $offset);
        $full = $parser->parseAll($source, $offset);
        $this->assertSame($prefixSuccess, $prefix->success);
        $this->assertSame($prefixSuccess ? $length : 0, $prefix->consumedByteLength);
        $this->assertSame($allSuccess, $full->success);
        $this->assertSame($offset, $full->start);
        $this->assertSame($allSuccess ? $source->byteLength() : $offset, $full->end);
        $this->assertSame($allSuccess ? $source->byteLength() - $offset : 0, $full->consumedByteLength);
        if ($prefixSuccess && !$allSuccess) {
            $this->assertSame($offset + $length, $full->farthestFailure);
            $this->assertNull($full->token());
            $this->assertSame([], $full->results());
        }
    }

    public function consumptionData()
    {
        return [
            [new Text('a'), new Utf8Source('ab'), 0, true, 1, false],
            [new Text('a'), new Utf8Source('a'), 0, true, 1, true],
            [new Text('a'), new Utf8Source('a '), 0, true, 1, false],
            [new Text('a'), new Utf8Source(' a'), 0, false, 0, false],
            [new Text('a'), new Utf8Source(''), 0, false, 0, false],
            [new Nothing(), new Utf8Source(''), 0, true, 0, true],
            [new Nothing(), new Utf8Source('a'), 0, true, 0, false],
            [new Nothing(), new Utf8Source("\u{00E9}"), 2, true, 0, true],
            [new Text('a'), new Utf8Source("\u{00E9}a"), 2, true, 1, true],
            [new Text("\u{00E9}"), new Utf8Source("\u{00E9}a"), 0, true, 2, false],
            [new Text("\xFF"), new ByteSource("\xFF"), 0, true, 1, true],
            [new Text("\xFF"), new ByteSource("\xFF\0"), 0, true, 1, false],
        ];
    }

    public function testCompletionIsCheckedBeforeCallbacksAssignmentsAndLazyOutput()
    {
        $events = [];
        $assigned = 'original';
        $maps = 0;
        $parser = (new Text('a'))->assignTo($assigned)->setResult('letter', function ($text) use (&$maps) {
            $maps++;
            return $text;
        })->callback(function () use (&$events) { $events[] = 'accepted'; });
        $failed = $parser->parseAll(new Utf8Source('ab'));
        $this->assertFalse($failed->success);
        $this->assertSame([], $events);
        $this->assertSame('original', $assigned);
        $this->assertSame([], $failed->results());
        $this->assertSame(0, $maps);
        $success = $parser->parseAll(new Utf8Source('a'));
        $this->assertSame([], $events);
        $this->assertTrue($success->commit());
        $this->assertSame(['accepted'], $events);
        $this->assertSame('a', $assigned);
        $this->assertSame(0, $maps);
        $this->assertSame('a', $success->result('letter'));
        $this->assertSame(1, $maps);
    }

    public function testRootSpacingDoesNotImplicitlyAcceptTrailingTrivia()
    {
        $events = [];
        $spacer = Repeat::star(' ')->callback(function () use (&$events) { $events[] = 'space'; });
        $body = (new Sequence('a', 'b'))->spacing($spacer)->token('body')
            ->callback(function () use (&$events) { $events[] = 'body'; });
        $this->assertFalse($body->parseAll(new Utf8Source('a b '))->success);
        $this->assertSame([], $events);
        $accepted = $body->parseAll(new Utf8Source('a b'));
        $this->assertTrue($accepted->success);
        $this->assertTrue($accepted->commit());
        $this->assertSame(['space', 'body'], $events);
        $events = [];
        $grammar = new Sequence($body, Repeat::star(' '));
        $full = $grammar->parseAll(new Utf8Source('a b  '));
        $this->assertTrue($full->success);
        $this->assertSame('a b  ', $full->rawText());
        $this->assertSame(5, $full->consumedByteLength);
        $this->assertTrue($full->commit());
        $this->assertSame(['space', 'body'], $events);
    }

    public function testLeadingAndTrailingTriviaMustBeInTheGrammar()
    {
        $grammar = new Sequence(Repeat::star(' '), new Text("\u{00E9}"), Repeat::star("\u{00A0}"));
        $source = new Utf8Source(" \u{00E9}\u{00A0}\u{00A0}");
        $result = $grammar->parseAll($source);
        $this->assertTrue($result->success);
        $this->assertSame(7, $result->consumedByteLength);
        $this->assertSame($source->sliceBytes(0, 7), $result->rawText());
    }

    public function testFullInputDoesNotRetrySuccessfulChoice()
    {
        $parser = Choice::first('a', 'ab');
        $this->assertTrue($parser->matchPrefix(new Utf8Source('ab'))->success);
        $this->assertFalse($parser->parseAll(new Utf8Source('ab'))->success);
        $this->assertTrue(Choice::longest('a', 'ab')->parseAll(new Utf8Source('ab'))->success);
    }

    public function testFullInputFailureKeepsFarthestObservedPosition()
    {
        $result = Choice::first('abcd', 'a')->parseAll(new Utf8Source('abcx'));
        $this->assertFalse($result->success);
        $this->assertSame(0, $result->consumedByteLength);
        $this->assertSame(3, $result->farthestFailure);
    }

    public function testPrefixAliasPreservesExistingSourceAndLegacyBehavior()
    {
        $parser = new Text('a');
        $source = new Utf8Source('ab');
        $this->assertResult(true, 1, $parser->match('ab'));
        $this->assertResult(true, 1, $parser->matchSource($source));
        $this->assertSame($parser->parseSource($source)->rawText(), $parser->matchPrefix($source)->rawText());
    }

    public function testAdapterCompletionRetainsDeclaredUnitsAndDeferredEffects()
    {
        $calls = 0;
        $parser = (new Text("\u{00E9}"))->callback(function () use (&$calls) { $calls++; });
        $adapter = new LegacyParserAdapter($parser, CoordinatePolicy::CODE_POINTS);
        $this->assertResult(true, 1, $adapter->matchPrefix("\u{00E9}a"));
        $calls = 0;
        $failed = $adapter->parseAll("\u{00E9}a");
        $this->assertResult(false, 1, $failed);
        $this->assertSame(0, $calls);
        $this->assertSame(2, $failed->farthestFailure);
        $this->assertSame(0, $failed->span->byteLength());
        $this->assertResult(true, 1, $adapter->parseAll("\u{00E9}"));
        $this->assertSame(1, $calls);
    }

    public function testAsciiAdapterStillSupportsUnmigratedSubclassForFullInput()
    {
        $custom = new class('a') extends Text {};
        $adapter = new LegacyParserAdapter($custom);
        $this->assertResult(true, 1, $adapter->parseAll('a'));
        $this->assertResult(false, 1, $adapter->parseAll('ab'));
    }

    public function testFullInputRejectsInteriorTextStart()
    {
        $this->expectException(InvalidArgumentException::class);
        (new Nothing())->parseAll(new Utf8Source("\u{00E9}"), 1);
    }
}
