<?php

declare(strict_types=1);

namespace Tests\Src\Parser;

use Error;
use Tests\ParserTestCase;
use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Compatibility\CoordinatePolicy;
use Vanderlee\Comprehend\Compatibility\LegacyParserAdapter;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;

class AstTest extends ParserTestCase
{
    public function testUnnamedRootPromotesNamedNestedBranchesAndKeepsPunctuation()
    {
        $left = (new Text('é'))->token('letter', 'test');
        $right = (new Text('a'))->token('letter');
        $pair = (new Sequence($left, ',', $right))->token('pair');
        $result = (new Sequence('(', new Sequence($pair), ')'))->parseAll(new Utf8Source('(é,a)'));
        $ast = $result->ast();
        $this->assertNull($ast->name);
        $this->assertSame('(é,a)', $ast->rawText());
        $this->assertSame($result->span, $ast->span);
        $this->assertCount(1, $ast->children);
        $pairNode = $ast->children[0];
        $this->assertSame('pair', $pairNode->name);
        $this->assertSame('é,a', $pairNode->rawText());
        $this->assertSame([1, 5], [$pairNode->span->start, $pairNode->span->end]);
        $this->assertSame(['letter', 'letter'], array_map(fn ($node) => $node->name, $pairNode->children));
        $this->assertSame('test', $pairNode->children[0]->group);
        $this->assertSame('é', $pairNode->children[0]->rawText());
        $this->assertSame([1, 3], [$pairNode->children[0]->span->start, $pairNode->children[0]->span->end]);
        $this->assertSame($ast, $result->ast());
    }

    public function testCompletelyUnnamedAndEmptyInputKeepSyntheticRoot()
    {
        foreach ([[new Sequence('(', 'a', ')'), '(a)'], [new Nothing(), '']] as [$parser, $text]) {
            $result = $parser->parseAll(new Utf8Source($text));
            $this->assertNull($result->ast()->name);
            $this->assertSame([], $result->ast()->children);
            $this->assertSame($text, $result->ast()->rawText());
            $this->assertSame($result->span, $result->ast()->span);
        }
    }

    public function testNamedRootAndEmptyNamedChild()
    {
        $result = (new Sequence('a', (new Nothing())->token('empty')))->token('root')
            ->parseAll(new Utf8Source('a'));
        $this->assertSame('root', $result->ast()->name);
        $this->assertCount(1, $result->ast()->children);
        $empty = $result->ast()->children[0];
        $this->assertSame(['empty', 1, 1, ''], [$empty->name, $empty->span->start, $empty->span->end, $empty->rawText()]);
    }

    public function testTriviaIsOmittedIncludingItsNamedDescendants()
    {
        $trivia = (new Text(' '))->token('space')->trivia();
        $body = (new Sequence((new Text('a'))->token('a'), (new Text('b'))->token('b')))->spacing($trivia);
        $result = (new Sequence($trivia, $body, $trivia))->parseAll(new Utf8Source(' a b '));
        $this->assertSame(['a', 'b'], array_map(fn ($node) => $node->name, $result->ast()->children));
        $this->assertSame(' a b ', $result->ast()->rawText());
        $this->assertTrue($result->node->children[0]->isTrivia);
    }

    public function testTerminalNamingHidesAstChildrenButPreservesConcreteTree()
    {
        $result = (new Sequence((new Text('a'))->token('child'), 'b'))->token('terminal', null, true)
            ->parseAll(new Utf8Source('ab'));
        $this->assertSame('terminal', $result->ast()->name);
        $this->assertSame([], $result->ast()->children);
        $this->assertCount(2, $result->node->children);
        $this->assertSame('ab', $result->ast()->rawText());
    }

    public function testNamingSnapshotAndAstReadsDoNotEvaluateResults()
    {
        $calls = 0;
        $parser = (new Text('é'))->token('original')->setResult(null, function () use (&$calls) {
            ++$calls;
            return 'changed';
        });
        $match = $parser->matchSourceUnresolved(new Utf8Source('é'));
        $parser->token('later');
        $ast = $match->node->ast();
        $this->assertSame(0, $calls);
        $this->assertSame('original', $ast->name);
        $this->assertSame('é', $ast->rawText());
        $this->assertSame('changed', $match->result);
        $this->assertSame('é', $ast->rawText());
    }

    public function testProcessedNamedRulesKeepStructure()
    {
        $definition = new Definition(new Sequence((new Text('a'))->token('item'), 'b'));
        $definition->addProcessor('value', fn () => 42);
        $rules = new Ruleset();
        $rules->define('document', $definition);
        $result = $rules->document()->parseAll(new Utf8Source('ab'));
        $this->assertSame(['value' => 42], $result->results());
        $this->assertSame('document', $result->ast()->name);
        $this->assertSame('item', $result->ast()->children[0]->name);
    }

    public function testChoiceIncludesOnlySelectedName()
    {
        $result = Choice::longest((new Text('a'))->token('short'), (new Text('ab'))->token('long'))
            ->matchPrefix(new Utf8Source('abc'));
        $this->assertCount(1, $result->ast()->children);
        $this->assertSame('long', $result->ast()->children[0]->name);
        $this->assertSame('ab', $result->ast()->rawText());
    }

    public function testFailureAndAdapterAndArbitraryBytes()
    {
        $parser = (new Text('é'))->token('letter');
        $this->assertNull($parser->parseAll(new Utf8Source('éx'))->ast());
        $adapter = new LegacyParserAdapter($parser, CoordinatePolicy::CODE_POINTS);
        $ast = $adapter->matchPrefix(new Utf8Source('zé'), 1)->node->ast();
        $this->assertSame([1, 3, 'é'], [$ast->span->start, $ast->span->end, $ast->rawText()]);
        $ast = (new Text("\xff"))->token(0)->parseAll(new ByteSource("\xff"))->ast();
        $this->assertSame(0, $ast->name);
        $this->assertSame("\xff", $ast->rawText());
    }

    public function testAstIsImmutable()
    {
        $ast = (new Text('a'))->token('a')->parseAll(new Utf8Source('a'))->ast();
        $this->expectException(Error::class);
        $ast->children[] = $ast;
    }
}
