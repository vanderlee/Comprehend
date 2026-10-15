<?php

declare(strict_types=1);

namespace Tests\features\authoring;

use PHPUnit\Framework\TestCase;
use TypeError;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ChoicePolicy;

class ChoiceConvenienceTest extends TestCase
{
    public function testFluentPolicyRequiresAnEnumCase(): void
    {
        $this->expectException(TypeError::class);

        C::choice('a', 'ab')->prefer('longest');
    }

    public function testChainedAndNestedAnonymousChoicesFlattenWithoutMutation(): void
    {
        $base = C::text('a')->or('b');
        $grammar = $base->or(C::choice('c', 'd'))->or('e');
        $this->assertCount(5, iterator_to_array($grammar->parser()));
        $this->assertCount(2, iterator_to_array($base->parser()));
        foreach (['a', 'b', 'c', 'd', 'e'] as $input) {
            $this->assertTrue($grammar->parse($input)->ok);
        }
        $this->assertFalse($grammar->parse('f')->ok);
        $this->assertFalse($base->parse('c')->ok);
        $this->assertSame($base, $base->or());
    }

    public function testFlatteningHonorsInheritedRankingAndAlternativeOrder(): void
    {
        $grammar = C::choice(C::choice('a', 'ab'), C::choice('abc', 'abcd'));
        $this->assertSame('a', $grammar->matchPrefix('abcd')->output());
        $this->assertSame('abcd', $grammar->prefer(ChoicePolicy::LONGEST)->matchPrefix('abcd')->output());
        $this->assertSame('a', $grammar->prefer(ChoicePolicy::SHORTEST)->matchPrefix('abcd')->output());
        $left = C::text('ab')->named('left');
        $right = C::text('ab')->named('right');
        $selected = C::longest(C::choice($left, $right), 'a')->parse('ab')->ast();
        $this->assertNotNull($selected->firstNamed('left'));
        $this->assertNull($selected->firstNamed('right'));
    }

    public function testExplicitChoiceAndWrapperPoliciesRetainTheirBoundaries(): void
    {
        $first = C::first('a', 'ab')->or('abc')->prefer(ChoicePolicy::LONGEST);
        $this->assertSame('a', $first->matchPrefix('ab')->rawText());
        $longest = C::longest('ab', 'a')->or('abc')->prefer(ChoicePolicy::SHORTEST);
        $this->assertSame('ab', $longest->matchPrefix('ab')->rawText());
        $shortest = C::shortest('ab', 'a')->or('abc')->prefer(ChoicePolicy::LONGEST);
        $this->assertSame('a', $shortest->matchPrefix('ab')->rawText());
        $wrapped = C::choice('a', 'ab')->prefer(ChoicePolicy::FIRST)->or('abc')->prefer(ChoicePolicy::LONGEST);
        $this->assertSame('a', $wrapped->matchPrefix('ab')->rawText());
        $this->assertCount(2, iterator_to_array(C::first('a', 'b')->or('c')->parser()));
    }

    public function testDecoratedChoicesKeepGuardsValuesNamesAndSelectedEffects(): void
    {
        $effects = 0;
        $group = C::choice('a', 'b')->named('group')->tag('token')
            ->mapValue(static fn(ParseResult $r): string => strtoupper($r->rawText()))
            ->onCommit(static function () use (&$effects): void {
                ++$effects;
            });
        $result = $group->or('c')->parse('b');
        $this->assertSame('b', $result->output());
        $this->assertSame('B', $group->parse('b')->output());
        $this->assertSame('b', $result->ast()->firstNamed('group')->rawText());
        $this->assertTrue($result->ast()->firstNamed('group')->hasTag('token'));
        $this->assertSame(0, $effects);
        $this->assertTrue($result->commit());
        $this->assertSame(1, $effects);
        $this->assertSame('c', $group->or('c')->parse('c')->output());
        $this->assertSame(1, $effects);
        $rejected = C::choice('a', 'b')->guard(static fn(): bool => false);
        $this->assertFalse($rejected->or('c')->parse('a')->ok);
        $this->assertTrue($rejected->or('a')->parse('a')->ok);
    }

    public function testChoiceSubclassesAreNotFlattened(): void
    {
        $custom = new class (new Text('a'), new Text('b')) extends Choice {};
        $this->assertInstanceOf($custom::class, C::of($custom)->or('c')->parser()[0]);
    }

    public function testFlatteningPreservesUnicodeSpansAndFailureDiagnostics(): void
    {
        $nested = C::of(new Choice(new Choice(new Text('éx'), new Text('éy')), new Text('éz')));
        $flat = C::choice($nested);
        foreach ([ChoicePolicy::FIRST, ChoicePolicy::LONGEST, ChoicePolicy::SHORTEST] as $policy) {
            $expected = $nested->prefer($policy)->parse('é!');
            $actual = $flat->prefer($policy)->parse('é!');
            $this->assertFalse($actual->ok);
            $this->assertSame($expected->farthestFailure, $actual->farthestFailure);
            $this->assertSame($expected->diagnostic->byteOffset, $actual->diagnostic->byteOffset);
            $this->assertSame($expected->diagnostic->expected, $actual->diagnostic->expected);
            $success = $flat->prefer($policy)->parse('éy');
            $this->assertTrue($success->ok);
            $this->assertSame(3, $success->end);
            $this->assertSame('éy', $success->output());
        }
    }

    public function testSimpleResultAccessUsesTheSameLazyValueWithoutCommitting(): void
    {
        $calls = 0;
        $effects = 0;
        $parser = C::text('42')->mapValue(static function (ParseResult $r) use (&$calls): int {
            ++$calls;

            return (int) $r->rawText();
        })->validateSemantic(static fn(): bool => false)
            ->onCommit(static function () use (&$effects): void {
                ++$effects;
            });
        $result = $parser->parse('42');
        $this->assertTrue($result->ok);
        $this->assertSame($result->success, $result->ok);
        $this->assertSame(0, $calls);
        $this->assertSame(42, $result->output());
        $this->assertSame(42, $result->value());
        $this->assertSame(42, $result->output());
        $this->assertSame(1, $calls);
        $this->assertSame(0, $effects);
        $this->assertFalse($result->isCommitted());
        $this->assertFalse($result->commit());
        $this->assertSame('42', $result->rawText());
        $failure = $parser->parse('no');
        $this->assertFalse($failure->ok);
        $this->assertNull($failure->output());
        $this->assertSame(1, $calls);
        $this->assertNull(C::text('x')->mapValue(static fn() => null)->parse('x')->output());
        $this->assertSame('x', C::text('x')->parse('x')->output());
    }
}
