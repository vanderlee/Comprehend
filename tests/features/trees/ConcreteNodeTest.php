<?php

declare (strict_types=1);

namespace Tests\features\trees;

use Error;
use InvalidArgumentException;
use Tests\ParserTestCase;
use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Directive\Space;
use Vanderlee\Comprehend\Parser\Structure\All;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Except;
use Vanderlee\Comprehend\Parser\Structure\Not;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\End;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;
use Vanderlee\Comprehend\Tree\ConcreteNode;

class ConcreteNodeTest extends ParserTestCase
{
    /** @dataProvider emptyParsers */
    public function testEmptyRootsRetainValidNodes($parser)
    {
        $result = $parser->parseAll(new Utf8Source(''));
        $this->assertTrue($result->success);
        $this->assertSame([0, 0, ''], [$result->node->span->start, $result->node->span->end, $result->node->rawText()]);
        $this->assertSame([], $result->node->children);
    }
    public function emptyParsers(): array
    {
        return [[new Nothing()], [new End()], [Repeat::optional(new Text('a'))], [Repeat::star(new Text('a'))]];
    }
    public function testTreeRetainsUnnamedAndEmptyChildrenAtBytePositions()
    {
        $source = new Utf8Source('zéaa');
        $result = (new Sequence(new Text('é'), Repeat::plus(new Text('a')), new Nothing(), new End()))->parseAll($source, 1);
        $root = $result->node;
        $this->assertSame($result->span, $root->span);
        $this->assertSame(Sequence::class, $root->kind);
        $this->assertCount(4, $root->children);
        $this->assertSame('éaa', $root->rawText());
        $this->assertSame('é', $root->children[0]->rawText());
        $this->assertCount(2, $root->children[1]->children);
        $this->assertSame(
            [5, 5, ''],
            [$root->children[2]->span->start, $root->children[2]->span->end, $root->children[2]->rawText()],
        );
        $this->assertSame(End::class, $root->children[3]->kind);
        $this->assertRawSpans($root, $source);
    }
    private function assertRawSpans(ConcreteNode $node, $source): void
    {
        $this->assertSame($source, $node->span->source);
        $this->assertSame($source->sliceBytes($node->span->start, $node->span->end), $node->rawText());
        foreach ($node->children as $child) {
            $this->assertRawSpans($child, $source);
        }
    }
    public function testProcessedRulesRetainConcreteChildren()
    {
        $definition = new Definition(new Sequence(new Text('a'), new Text('b')));
        $definition->addProcessor('value', fn() => 42);
        $result = $definition->build()->parseAll(new Utf8Source('ab'));
        $this->assertSame(['value' => 42], $result->results());
        $this->assertCount(1, $result->node->children);
        $this->assertCount(2, $result->node->children[0]->children);
        $this->assertSame('ab', $result->node->rawText());
    }
    public function testChoiceRetainsOnlySelectedAlternative()
    {
        $result = Choice::longest(new Text('a'), new Text('ab'))->matchPrefix(new Utf8Source('abc'));
        $this->assertCount(1, $result->node->children);
        $this->assertSame('ab', $result->node->children[0]->rawText());
        $this->assertSame('ab', $result->node->rawText());
    }
    public function testIntersectionRetainsOverlappingRecognitionChildren()
    {
        $result = (new All(new Text('a'), new Text('abc')))->matchPrefix(new Utf8Source('abc'));
        $this->assertSame('a', $result->node->rawText());
        $this->assertSame(['a', 'abc'], array_map(fn($node) => $node->rawText(), $result->node->children));
    }
    public function testLookaheadHasEmptyNodeAndExclusionKeepsOnlyRequiredChild()
    {
        $result = (new Not(new Text('b')))->matchPrefix(new Utf8Source('a'));
        $this->assertSame('', $result->node->rawText());
        $this->assertSame([], $result->node->children);
        $result = (new Except(new Text('a'), new Text('b')))->parseAll(new Utf8Source('a'));
        $this->assertCount(1, $result->node->children);
        $this->assertSame('a', $result->node->children[0]->rawText());
    }
    public function testDirectivesWrapChildAndSpacingIsInRawSpan()
    {
        $result = (new Space(new Text(' '), new Sequence(new Text('a'), new Text('b'))))->parseAll(new Utf8Source('a b'));
        $this->assertSame(Space::class, $result->node->kind);
        $this->assertSame(Sequence::class, $result->node->children[0]->kind);
        $this->assertSame('a b', $result->node->rawText());
        $this->assertCount(3, $result->node->children[0]->children);
        $this->assertTrue($result->node->children[0]->children[1]->isTrivia);
        $this->assertSame(2, $result->node->children[0]->children[2]->span->start);
    }
    public function testNodeAndChildrenAreImmutable()
    {
        $node = (new Sequence(new Text('a'), new Text('b')))->parseAll(new Utf8Source('ab'))->node;
        try {
            $node->kind = 'changed';
            $this->fail('Kind must be readonly');
        } catch (Error $error) {
            $this->assertStringContainsString('readonly', $error->getMessage());
        }
        try {
            $node->children[] = $node;
            $this->fail('Children must be readonly');
        } catch (Error $error) {
            $this->assertStringContainsString('readonly', $error->getMessage());
        }
        $this->assertSame('ab', $node->rawText());
    }
    /** @dataProvider invalidChildren */
    public function testInvalidChildrenAreRejected($children)
    {
        $this->expectException(InvalidArgumentException::class);
        new ConcreteNode('root', (new Utf8Source('a'))->span(0, 1), $children);
    }
    public function invalidChildren(): array
    {
        return [
            [['invalid']],
            [[new ConcreteNode('foreign', (new Utf8Source('a'))->span(0, 1))]],
            [['named' => new ConcreteNode('child', (new Utf8Source('a'))->span(0, 1))]],
        ];
    }
}
