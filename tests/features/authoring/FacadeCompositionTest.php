<?php

declare(strict_types=1);

namespace Tests\features\authoring;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;

class FacadeCompositionTest extends TestCase
{
    public function testAnonymousPrefixesFlattenWithoutMutatingReusedExpressions(): void
    {
        $base = C::text('a')->text('b');
        $grammar = $base->text('c')->then('d');
        $this->assertCount(4, iterator_to_array($grammar->parser()));
        $this->assertCount(2, iterator_to_array($base->parser()));
        $this->assertTrue($grammar->parse('abcd')->success);
        $this->assertFalse($grammar->parse('abce')->success);
        $this->assertTrue($base->parse('ab')->success);
        $this->assertTrue(C::sequence($base, 'c')->parse('abc')->success);
    }

    public function testMetadataBoundariesRetainTheirSemantics(): void
    {
        $effects = 0;
        $prefix = C::sequence('a', 'b')->named('pair')->tag('group')
            ->mapValue(static fn(ParseResult $result): string => strtoupper($result->rawText()))
            ->setResult('pair')
            ->onCommit(static function () use (&$effects): void {
                ++$effects;
            });
        $result = $prefix->text('c')->parse('abc');
        $this->assertTrue($result->success);
        $this->assertSame('ab', $result->ast()->firstNamed('pair')->rawText());
        $this->assertTrue($result->ast()->firstNamed('pair')->hasTag('group'));
        $this->assertSame('ab', $result->result('pair'));
        $this->assertSame('AB', $prefix->parse('ab')->value());
        $this->assertSame(0, $effects);
        $this->assertTrue($result->commit());
        $this->assertSame(1, $effects);
        $guarded = C::sequence('a', 'b')->guard(static fn(): bool => false);
        $this->assertFalse($guarded->text('c')->parse('abc')->success);
        $validated = C::sequence('a', 'b')->validateSemantic(static fn(): bool => false);
        $this->assertFalse($validated->text('c')->parse('abc')->validate()->valid);
    }

    public function testRawSpacingPoliciesAndSubclassBoundariesArePreserved(): void
    {
        $prefix = (new Sequence(new Text('a'), new Text('b')))->spacing(null);
        $grammar = C::of($prefix)->text('c');
        $this->assertTrue($grammar->parse('ab c', spacing: C::text(' '))->success);
        $this->assertFalse($grammar->parse('a b c', spacing: C::text(' '))->success);
        $custom = new class (new Text('a'), new Text('b')) extends Sequence {};
        $this->assertInstanceOf($custom::class, C::of($custom)->text('c')->parser()[0]);
    }

    public function testNonPrefixNullableGroupsKeepSpacingConsumption(): void
    {
        $group = C::sequence(C::nothing(), 'b');
        $grammar = C::sequence('a', $group);
        $this->assertTrue($grammar->parse('a  b', spacing: C::text(' '))->success);
        $this->assertFalse($grammar->parse('a b', spacing: C::text(' '))->success);
        $empty = new Sequence(new Text('x'));
        unset($empty[0]);
        $this->assertTrue(C::sequence($empty, 'b')->parse(' b', spacing: C::text(' '))->success);
    }

    public function testPerCallSpacingDoesNotChangeTheGrammarAndHonorsLocalPolicies(): void
    {
        $grammar = C::text('a')->text('b')->text('c');
        $space = C::text(' ')->many();
        $result = $grammar->parse('a b c', spacing: $space);
        $this->assertTrue($result->success);
        $this->assertSame('a b c', $result->rawText());
        $this->assertFalse($grammar->parse('a b c')->success);
        $this->assertTrue($grammar->parseAll('a b c', spacing: $space)->success);
        $this->assertSame('a b c', $grammar->matchPrefix('a b c!', spacing: $space)->rawText());
        $this->assertFalse($grammar->parse(' a b c', spacing: $space)->success);
        $this->assertFalse($grammar->parse('a b c ', spacing: $space)->success);
        $local = C::sequence('a', 'b')->spacing(C::text('_'))->text('c');
        $this->assertTrue($local->parse('a_b c', spacing: $space)->success);
        $this->assertFalse($local->parse('a b c', spacing: $space)->success);
        $tight = C::sequence('a', 'b')->lexeme()->text('c');
        $this->assertTrue($tight->parse('ab c', spacing: $space)->success);
        $this->assertFalse($tight->parse('a b c', spacing: $space)->success);
        $this->assertTrue($grammar->parse('xa b c', byteOffset: 1, spacing: $space)->success);
    }
}
