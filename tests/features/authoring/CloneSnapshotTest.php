<?php

declare(strict_types=1);

namespace Tests\features\authoring;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\AbstractRuleset;
use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Builder\Implementation;
use Vanderlee\Comprehend\Directive;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\All;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Except;
use Vanderlee\Comprehend\Parser\Structure\Not;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Stub;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ChoicePolicy;
use Vanderlee\Comprehend\Source\ByteSource;

final class CloneSnapshotTest extends TestCase
{
    public function testEveryBuiltInDetachesItsOwnedOperandsAndPolicies(): void
    {
        $a = new Text('a');
        $b = new Text('b');
        $spacer = new Text(' ');
        $parsers = [
            new Directive\CaseSensitive(true, $a),
            new Directive\Prefer(ChoicePolicy::FIRST, $a),
            new Directive\Lexeme($a),
            new Directive\Trivia($a),
            new Directive\Space($spacer, $a),
            (new Sequence($a))->spacing($spacer),
            new Choice($a, $b),
            new All($a, $a),
            new Except($a, $b),
            new Not($b),
            Repeat::plus($a)->spacing($spacer),
            (new Stub())->setParser($a),
        ];
        $context = new GraphSnapshot();
        $copies = [];
        foreach ($parsers as $parser) {
            $copy = $context->copy($parser);
            $copies[] = $copy;
            $this->assertCount(count($parser->children()), $copy->children());
            foreach ($parser->children() as $index => $child) {
                $this->assertNotSame($child, $copy->children()[$index]);
                $this->assertSame($context->copy($child), $copy->children()[$index]);
            }
        }
        $a->guard(static fn(): bool => false);
        $spacer->guard(static fn(): bool => false);

        foreach ($copies as $copy) {
            $this->assertTrue($copy->matchPrefix(new ByteSource('a'))->success, $copy::class);
        }
    }

    public function testCloneHooksRunOncePerSharedNodeWithoutRerunningConstructors(): void
    {
        $tracker = (object) ['constructors' => 0, 'clones' => 0];
        $parser = new class ($tracker) extends Text {
            public readonly string $label;
            public string $uninitialized;

            public function __construct(private object $tracker)
            {
                parent::__construct('a');
                $this->label = 'kept';
                ++$this->tracker->constructors;
            }

            public function __clone()
            {
                ++$this->tracker->clones;
            }
        };
        $copy = (new GraphSnapshot())->copy(new Sequence($parser, $parser));
        $children = $copy->children();

        $this->assertSame(1, $tracker->constructors);
        $this->assertSame(1, $tracker->clones);
        $this->assertSame($children[0], $children[1]);
        $this->assertSame('kept', $children[0]->label);
        $this->assertFalse(isset($children[0]->uninitialized));
        $this->assertTrue($copy->parseAll(new ByteSource('aa'))->success);
    }

    public function testSubclassHookCopiesItsOwnAndInheritedDependenciesAndMetadata(): void
    {
        $child = new Text('a');
        $parser = new class ($child) extends Sequence {
            public function __construct(private Parser $operand)
            {
                parent::__construct($operand);
            }

            protected function copyDependencies(GraphSnapshot $context): void
            {
                parent::copyDependencies($context);
                $this->operand = $context->copy($this->operand);
            }

            public function operand(): Parser
            {
                return $this->operand;
            }
        };
        $parser->name('word')->tag('original')->mapValue(static fn(): string => 'value');
        $context = new GraphSnapshot();
        $copy = $context->copy($parser);
        $child->guard(static fn(): bool => false);
        $parser->name('changed')->tag('later')->mapValue(static fn(): string => 'changed');
        $result = $copy->parseAll(new ByteSource('a'));

        $this->assertNotSame($child, $copy->children()[0]);
        $this->assertSame($copy->children()[0], $copy->operand());
        $this->assertTrue($result->success);
        $this->assertSame('word', $result->node->name);
        $this->assertSame(['original'], $result->node->tags);
        $this->assertSame('value', $result->value());
    }

    public function testImplementationCopiesNestedParserArgumentsBeforeFactoryMaterialization(): void
    {
        $argument = new Text('a');
        $calls = 0;
        $definition = new Definition(static function (array $arguments) use (&$calls): Parser {
            ++$calls;

            return $arguments['parser'];
        });
        $implementation = new Implementation($definition, [['parser' => $argument]]);
        $copy = (new GraphSnapshot(false))->copy($implementation);
        $argument->guard(static fn(): bool => false);

        $this->assertSame(0, $calls);
        $this->assertTrue($copy->parseAll(new ByteSource('a'))->success);
        $this->assertSame(1, $calls);
        $this->assertFalse($implementation->parseAll(new ByteSource('a'))->success);
    }

    public function testDefinitionGeneratorCyclesReuseRegisteredDestinations(): void
    {
        $definition = new Definition(new Text('a'));
        $implementation = new Implementation($definition);
        $definition->setGenerator($implementation);
        $context = new GraphSnapshot(false);
        $copy = $context->copyDefinition($definition);

        $this->assertNotSame($definition, $copy);
        $this->assertNotSame($implementation, $copy->generator);
        $this->assertSame($context->copy($implementation), $copy->generator);
        $this->assertSame($copy, $context->copyDefinition($definition));
    }

    public function testResolvingRulesetInvokesSubclassDependencyHookAfterPruning(): void
    {
        $extra = new Text('b');
        $rules = new class ($extra) extends AbstractRuleset {
            public function __construct(private Parser $extra)
            {
                parent::__construct();
                $this->root(new Text('a'));
            }

            protected function copyDependencies(GraphSnapshot $context): void
            {
                parent::copyDependencies($context);
                $this->extra = $context->copy($this->extra);
            }

            public function extra(): Parser
            {
                return $this->extra;
            }
        };
        $copy = (new GraphSnapshot())->copy($rules);
        $extra->guard(static fn(): bool => false);

        $this->assertNotSame($extra, $copy->extra());
        $this->assertTrue($copy->extra()->parseAll(new ByteSource('b'))->success);
        $this->assertTrue($copy->parseAll(new ByteSource('a'))->success);
    }
}
