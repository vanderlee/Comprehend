<?php

declare(strict_types=1);

namespace Tests\Src\Compatibility;

use InvalidArgumentException;
use LogicException;
use Tests\ParserTestCase;
use Vanderlee\Comprehend\Compatibility\CoordinatePolicy;
use Vanderlee\Comprehend\Compatibility\LegacyParserAdapter;
use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Match\Failure;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Char;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;

class LegacyParserAdapterTest extends ParserTestCase
{
    /** @dataProvider coordinateData */
    public function testConsistentCoordinatesForCallbacksOutputsAndEmptyChildren($policy, $inputOffset, $length, $childOffset)
    {
        $events = [];
        $assigned = null;
        $parser = new Sequence((new Char("\u{00E9}"))->token('letter')->setResult('letter')->assignTo($assigned)
            ->callback(function ($text, $input, $offset, $length) use (&$events) { $events[] = [$text, $offset, $length]; }),
            (new Text('a'))->token('tail'), (new Nothing())->token('empty'));
        $source = new Utf8Source("z\u{00E9}a");
        $result = (new LegacyParserAdapter($parser, $policy))->match($source, $inputOffset);
        $this->assertInstanceOf(Success::class, $result);
        $this->assertResult(true, $length, $result);
        $this->assertSame(["\u{00E9}", 1, $policy === CoordinatePolicy::BYTES ? 2 : 1], $events[0]);
        $this->assertSame("\u{00E9}", $assigned);
        $this->assertSame(['letter' => "\u{00E9}"], $result->results);
        $token = $result->token;
        $this->assertSame("\u{00E9}a", $token->text);
        $this->assertSame(1, $token->offset);
        $this->assertSame($length, $token->length);
        $this->assertSame($childOffset, $token->children[1]->offset);
        $this->assertSame($childOffset + 1, $token->children[2]->offset);
        $this->assertSame(0, $token->children[2]->length);
        $this->assertSame('', $token->children[2]->text);
        $this->assertSame($childOffset, $token->toArray()['children'][1]['offset']);
        $this->assertSame($childOffset, json_decode(json_encode($token), true)['children'][1]['offset']);
        $this->assertSame("\u{00E9}a", $token->toXml()->documentElement->textContent);
        $this->assertSame($source, $result->span->source);
        $this->assertSame([1, 4], [$result->span->start, $result->span->end]);
        $result->results;
        $result->token;
        $this->assertCount(1, $events);
    }

    public function coordinateData()
    {
        return [[CoordinatePolicy::BYTES, 1, 3, 3], [CoordinatePolicy::CODE_POINTS, 1, 2, 2]];
    }

    public function testCharacterOffsetAfterMultibytePrefixIsExplicit()
    {
        $parser = new Text('a');
        $source = new Utf8Source("\u{00E9}a");
        $bytes = (new LegacyParserAdapter($parser, CoordinatePolicy::BYTES))->match($source, 2);
        $characters = (new LegacyParserAdapter($parser, CoordinatePolicy::CODE_POINTS))->match($source, 1);
        $this->assertSame([2, 1, 'a'], [$bytes->token->offset, $bytes->length, $bytes->token->text]);
        $this->assertSame([1, 1, 'a'], [$characters->token->offset, $characters->length, $characters->token->text]);
        $this->assertSame(2, $characters->span->start);
    }

    public function testDefaultAsciiPolicyPreservesShapesAndUnmigratedSubclass()
    {
        $custom = new class('a') extends Text {
            protected function parse(&$input, $offset, Context $context) { return parent::parse($input, $offset, $context); }
        };
        $spacer = new class(' ') extends Text {};
        $parser = (new Sequence($custom, 'b'))->spacing($spacer)->setResult('word')->token('word');
        $legacy = $parser->match('a b');
        $adapted = (new LegacyParserAdapter($parser))('a b');
        $this->assertResult(true, $legacy->length, $adapted);
        $this->assertSame($legacy->token->toArray(), $adapted->token->toArray());
        $this->assertSame($legacy->results, $adapted->results);
    }

    public function testFailureLengthConvertsDiagnosticDistanceWithoutConsumption()
    {
        $parser = new Text("\u{00E9}ab");
        $source = new Utf8Source("z\u{00E9}ax");
        $bytes = (new LegacyParserAdapter($parser, CoordinatePolicy::BYTES))->match($source, 1);
        $characters = (new LegacyParserAdapter($parser, CoordinatePolicy::CODE_POINTS))->match($source, 1);
        $this->assertInstanceOf(Failure::class, $characters);
        $this->assertResult(false, 3, $bytes);
        $this->assertResult(false, 2, $characters);
        $this->assertSame([1, 1, 4], [$characters->span->start, $characters->span->end, $characters->farthestFailure]);
        $this->assertNull($characters->token);
        $this->assertSame([], $characters->results);
    }

    public function testFailureDistanceUsesFarthestBranchRatherThanShortestLegacyLength()
    {
        $parser = Choice::shortest('abcd', 'az');
        $this->assertSame(1, $parser->match('abcx')->length);
        $this->assertResult(false, 3, (new LegacyParserAdapter($parser))->match('abcx'));
    }

    public function testSelectedSpacingCallbacksConvertAndFailedParentHasNoEffects()
    {
        $events = [];
        $spacer = (new Char("\u{00A0}"))->callback(function ($text, $input, $offset, $length) use (&$events) {
            $events[] = [$text, $offset, $length];
        });
        $parser = (new Sequence("\u{00E9}", 'a'))->spacing($spacer);
        $adapter = new LegacyParserAdapter($parser, CoordinatePolicy::CODE_POINTS);
        $this->assertFalse($adapter->match("\u{00E9}\u{00A0}b")->match);
        $this->assertSame([], $events);
        $this->assertResult(true, 3, $adapter->match("\u{00E9}\u{00A0}a"));
        $this->assertSame([["\u{00A0}", 1, 1]], $events);
    }

    /** @dataProvider invalidPolicyData */
    public function testPoliciesRejectAmbiguousOrInvalidPositions($policy, $input, $offset)
    {
        $this->expectException(InvalidArgumentException::class);
        (new LegacyParserAdapter(new Nothing(), $policy))->match($input, $offset);
    }

    public function invalidPolicyData()
    {
        return [
            [CoordinatePolicy::ASCII, "\u{00E9}", 0],
            [CoordinatePolicy::ASCII, new ByteSource("\xFF"), 0],
            [CoordinatePolicy::CODE_POINTS, new ByteSource('abc'), 0],
            [CoordinatePolicy::CODE_POINTS, "\xFF", 0],
            [CoordinatePolicy::CODE_POINTS, "\u{00E9}", -1],
            [CoordinatePolicy::CODE_POINTS, "\u{00E9}", 2],
            [CoordinatePolicy::BYTES, new Utf8Source("\u{00E9}"), 1],
        ];
    }

    public function testBytePolicySupportsArbitraryOctets()
    {
        $this->assertSame("\xFF", (new LegacyParserAdapter(new Char(0xFF), CoordinatePolicy::BYTES))
            ->match("\xFF")->token->text);
    }

    public function testExplicitPolicyStillRequiresCustomSourceDeclaration()
    {
        $custom = new class('a') extends Text {};
        $this->expectException(LogicException::class);
        (new LegacyParserAdapter($custom, CoordinatePolicy::BYTES))->match('a');
    }

    public function testCodePointsAreNeitherUtf16UnitsNorGraphemes()
    {
        $text = "e\u{0301}";
        $result = (new LegacyParserAdapter(new Text($text), CoordinatePolicy::CODE_POINTS))
            ->match("\u{1F600}" . $text, 1);
        $this->assertResult(true, 2, $result);
        $this->assertSame([1, 2, $text], [$result->token->offset, $result->token->length, $result->token->text]);
        $this->assertSame([4, 7], [$result->span->start, $result->span->end]);
    }

    public function testUnconvertibleDiagnosticPositionFailsBeforeEffects()
    {
        $calls = 0;
        $custom = new class("\u{00E9}") extends Text {
            protected function supportsSource(): bool { return true; }
            protected function parse(&$input, $offset, Context $context)
            {
                $context->recordFailure(1); // byte position inside é
                return parent::parse($input, $offset, $context);
            }
        };
        $custom->callback(function () use (&$calls) { $calls++; });
        try {
            (new LegacyParserAdapter($custom, CoordinatePolicy::CODE_POINTS))->match("\u{00E9}");
            $this->fail('Expected explicit conversion error');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(0, $calls);
        }
    }
}
