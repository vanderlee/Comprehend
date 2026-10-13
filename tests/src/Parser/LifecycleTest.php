<?php

declare(strict_types=1);

namespace Tests\Src\Parser;

use RuntimeException;
use Tests\ParserTestCase;
use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Compatibility\LegacyParserAdapter;
use Vanderlee\Comprehend\Parser\Structure\All;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Tree\ConcreteNode;
use Vanderlee\Comprehend\Source\Utf8Source;

class LifecycleTest extends ParserTestCase
{
    public function testGuardRejectsCandidateWithoutSemanticOrCommittedEffects()
    {
        $events = [];
        $maps = 0;
        $rejected = (new Text('a'))->guard(fn (ConcreteNode $node): bool => $node->rawText() === 'b')
            ->setResult(null, function () use (&$maps) { ++$maps; return 'mapped'; })
            ->callback(function () use (&$events) { $events[] = 'rejected'; });
        $accepted = (new Text('a'))->callback(function () use (&$events) { $events[] = 'accepted'; });
        $result = Choice::first($rejected, $accepted)->parseAll(new Utf8Source('a'));
        $this->assertTrue($result->success);
        $this->assertSame([], $events);
        $this->assertSame(0, $maps);
        $this->assertTrue($result->commit());
        $this->assertTrue($result->commit());
        $this->assertSame(['accepted'], $events);
        $this->assertSame(0, $maps);
        $failure = $rejected->parseAll(new Utf8Source('a'));
        $this->assertFalse($failure->success);
        $this->assertSame(['guard(Text)'], $failure->diagnostic->expected);
    }

    public function testSemanticValidationFollowsSyntaxAndBlocksCommit()
    {
        $events = [];
        $parser = (new Text('42'))->mapValue(fn (ParseResult $result): int => (int) $result->rawText())
            ->validateSemantic(function (ParseResult $result) use (&$events) {
                $events[] = 'validate';
                return $result->value() < 10 ? true : 'Number is too large';
            })->callback(function () use (&$events) { $events[] = 'effect'; });
        $failed = $parser->parseAll(new Utf8Source('42x'));
        $this->assertFalse($failed->commit());
        $this->assertSame([], $events);
        $result = $parser->parseAll(new Utf8Source('42'));
        $this->assertTrue($result->success);
        $this->assertFalse($result->isCommitted());
        $this->assertSame([], $events);
        $report = $result->validate();
        $this->assertFalse($report->valid);
        $this->assertSame(['Number is too large'], $report->errors);
        $this->assertSame($report, $result->validate());
        $this->assertFalse($result->commit());
        $this->assertSame(['validate'], $events);
        $this->assertSame('42', $result->node->rawText());
    }

    public function testSelectedChildValidationAndEffectsRunInOrderOnce()
    {
        $events = [];
        $assigned = 'before';
        $child = (new Text('a'))->assignTo($assigned)
            ->validateSemantic(function () use (&$events) { $events[] = 'child validation'; return true; })
            ->callback(function () use (&$events) { $events[] = 'child'; });
        $spacer = (new Text(' '))->callback(function () use (&$events) { $events[] = 'space'; });
        $parser = (new Sequence($child, 'b'))->spacing($spacer)
            ->validateSemantic(function () use (&$events) { $events[] = 'root validation'; return true; })
            ->callback(function () use (&$events) { $events[] = 'root'; });
        $result = $parser->parseAll(new Utf8Source('a b'));
        $this->assertSame('before', $assigned);
        $this->assertTrue($result->commit());
        $this->assertTrue($result->isCommitted());
        $this->assertSame('a', $assigned);
        $this->assertSame(['child validation', 'root validation', 'child', 'space', 'root'], $events);
        $this->assertTrue($result->commit());
        $this->assertCount(5, $events);
    }

    public function testDefinitionValidatorsAreSemanticWhileLegacyAdaptersKeepOldTiming()
    {
        $calls = 0;
        $definition = new Definition(new Text('a'), function () use (&$calls) { ++$calls; return false; });
        $parser = Choice::first($definition()->token('first'), (new Text('a'))->token('second'));
        $result = $parser->parseAll(new Utf8Source('a'));
        $this->assertSame(0, $calls);
        $this->assertSame('first', $result->ast()->children[0]->name);
        $this->assertFalse($result->commit());
        $this->assertSame(1, $calls);
        $this->assertSame('second', (new LegacyParserAdapter($parser))->parseAll('a')->token->children[0]->name);
        $this->assertSame(2, $calls);
    }

    public function testAbandonedDefinitionsDoNotEvaluateTransformsProcessorsOrValidators()
    {
        $events = [];
        $definition = new Definition((new Text('a'))->setResult('item', function () use (&$events) {
            $events[] = 'transform'; return 'value';
        }), function () use (&$events) { $events[] = 'validate'; return true; });
        $definition->addProcessor('value', function () use (&$events) { $events[] = 'process'; return 42; });
        $abandoned = new Sequence($definition(), 'x');
        $parser = Choice::first($abandoned, new Text('ab'));
        $result = $parser->parseAll(new Utf8Source('ab'));
        $this->assertTrue($result->commit());
        $this->assertSame([], $events);
        $this->assertFalse($definition()->parseAll(new Utf8Source('ab'))->commit());
        $this->assertSame([], $events);
        $selected = $definition()->parseAll(new Utf8Source('a'));
        $this->assertSame([], $events);
        $this->assertSame(['value' => 42], $selected->results());
        $this->assertSame(['transform', 'process'], $events);
        $this->assertTrue($selected->commit());
        $this->assertSame(['transform', 'process', 'validate'], $events);
    }

    public function testSnapshotsExcludeLaterValidatorsCallbacksAndAssignments()
    {
        $events = [];
        $first = 'before';
        $later = 'later';
        $parser = (new Text('a'))->assignTo($first)->validateSemantic(fn () => true);
        $result = $parser->parseAll(new Utf8Source('a'));
        $parser->assignTo($later)->validateSemantic(fn () => false)
            ->callback(function () use (&$events) { $events[] = 'late'; });
        $this->assertTrue($result->commit());
        $this->assertSame('a', $first);
        $this->assertSame('later', $later);
        $this->assertSame([], $events);
        $this->assertFalse($parser->parseAll(new Utf8Source('a'))->commit());
    }

    public function testFailedEffectSequenceIsNotRetriedAndCannotClaimCommitted()
    {
        $calls = 0;
        $failure = new RuntimeException('effect failed');
        $result = (new Text('a'))->callback(function () use (&$calls, $failure) { ++$calls; throw $failure; })
            ->parseAll(new Utf8Source('a'));
        for ($i = 0; $i < 2; ++$i) {
            try { $result->commit(); $this->fail('Expected retained effect error'); }
            catch (RuntimeException $error) { $this->assertSame($failure, $error); }
        }
        $this->assertSame(1, $calls);
        $this->assertFalse($result->isCommitted());
    }

    public function testLegacySourceAndStringCallbacksRemainAutomatic()
    {
        $calls = 0;
        $parser = (new Text('a'))->callback(function () use (&$calls) { ++$calls; });
        $parser->match('a');
        $parser->matchSource(new Utf8Source('a'));
        (new LegacyParserAdapter($parser))->parseAll('a');
        $this->assertSame(3, $calls);
        $result = $parser->matchPrefix(new Utf8Source('a'));
        $this->assertSame(3, $calls);
        $this->assertTrue($result->commit());
        $this->assertSame(4, $calls);
    }

    public function testRequiredIntersectionValidationIsRetained()
    {
        $result = (new All(new Text('a'), (new Text('ab'))->validateSemantic(fn () => 'Intersection rejected')))
            ->matchPrefix(new Utf8Source('ab'));
        $this->assertTrue($result->success);
        $this->assertSame(['Intersection rejected'], $result->validate()->errors);
        $this->assertFalse($result->commit());
    }

    public function testValidationErrorsAreCachedAndPreventEffects()
    {
        $calls = 0;
        $effects = 0;
        $failure = new RuntimeException('validator failed');
        $result = (new Text('a'))->validateSemantic(function () use (&$calls, $failure) { ++$calls; throw $failure; })
            ->callback(function () use (&$effects) { ++$effects; })->parseAll(new Utf8Source('a'));
        for ($i = 0; $i < 2; ++$i) {
            try { $result->commit(); $this->fail('Expected cached validation error'); }
            catch (RuntimeException $error) { $this->assertSame($failure, $error); }
        }
        $this->assertSame(1, $calls);
        $this->assertSame(0, $effects);
    }

    public function testCanonicalSpacingUsesDeferredDefinitionSemantics()
    {
        $calls = 0;
        $definition = new Definition(new Text(' '), function () use (&$calls) { ++$calls; return false; });
        $parser = (new Sequence('a', 'b'))->spacing($definition());
        $result = $parser->parseAll(new Utf8Source('a b'));
        $this->assertTrue($result->success);
        $this->assertSame(0, $calls);
        $this->assertFalse($result->commit());
        $this->assertSame(1, $calls);
        $this->assertFalse((new LegacyParserAdapter($parser))->parseAll('a b')->match);
    }

    public function testRecursiveValidationIsRejected()
    {
        $result = (new Text('a'))->validateSemantic(fn (ParseResult $result) => $result->validate()->valid)
            ->parseAll(new Utf8Source('a'));
        $this->expectException(\LogicException::class);
        $result->validate();
    }

    public function testRecursiveCommitIsRejectedWithoutReplayingEffects()
    {
        $result = null;
        $calls = 0;
        $parser = (new Text('a'))->callback(function () use (&$result, &$calls) { ++$calls; $result->commit(); });
        $result = $parser->parseAll(new Utf8Source('a'));
        for ($i = 0; $i < 2; ++$i) {
            try { $result->commit(); $this->fail('Expected recursive commit failure'); }
            catch (\LogicException $error) { $this->assertSame('Recursive effect commit', $error->getMessage()); }
        }
        $this->assertSame(1, $calls);
        $this->assertFalse($result->isCommitted());
    }
}
