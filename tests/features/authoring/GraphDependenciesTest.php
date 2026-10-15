<?php

declare(strict_types=1);

namespace Tests\features\authoring;

use ArrayObject;
use LogicException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Builder\GraphAnalysis;
use Vanderlee\Comprehend\Builder\Implementation;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Directive;
use Vanderlee\Comprehend\Parser\Structure\All;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Except;
use Vanderlee\Comprehend\Parser\Structure\Not;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Stub;
use Vanderlee\Comprehend\Parser\Terminal\Text;

final class GraphDependenciesTest extends TestCase
{
    public function testBuiltInWrappersExposeStableOperandsWithoutSemanticCallbacks(): void
    {
        $calls = 0;
        $child = (new Text('a'))->mapValue(static function () use (&$calls): int {
            return ++$calls;
        });
        $wrappers = [
            new Directive\CaseSensitive(true, $child),
            new Directive\Prefer(Directive\Prefer::FIRST, $child),
            new Directive\Lexeme($child),
            new Directive\Trivia($child),
            new Directive\Space(null, $child),
            new Not($child),
            new Repeat($child, 0, 0),
            (new Stub())->setParser($child),
        ];

        foreach ($wrappers as $wrapper) {
            $this->assertSame([$child], $wrapper->children());
            $this->assertSame([$child], $wrapper->children());
        }
        $this->assertSame(0, $calls);
    }

    public function testContainersPreserveOperandOrderAndSharing(): void
    {
        $a = new Text('a');
        $b = new Text('b');
        foreach ([new Sequence($a, $b, $a), new Choice($a, $b, $a), new All($a, $b, $a)] as $parser) {
            $this->assertSame([$a, $b, $a], $parser->children());
        }
        $this->assertSame([$a, $b], (new Except($a, $b))->children());
    }

    public function testSparseOperandKeysProduceAList(): void
    {
        $a = new Text('a');
        $b = new Text('b');
        $parser = new Choice($a, $b);
        unset($parser[0]);

        $this->assertSame([$b], $parser->children());
    }

    public function testStoredSpacingPoliciesAreIncludedAndCanBeDisabled(): void
    {
        $child = new Text('a');
        $spacer = new Text(' ');
        foreach ([new Sequence($child), Repeat::star($child)] as $parser) {
            $this->assertSame([$child], $parser->children());
            $parser->spacing($spacer);
            $this->assertSame([$child, $spacer], $parser->children());
            $parser->spacing(null);
            $this->assertSame([$child], $parser->children());
        }
        $this->assertSame([$spacer, $child], (new Directive\Space($spacer, $child))->children());
    }

    public function testSpacingDependenciesAreValidatedEvenWhenNotLeading(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unresolved stub: Sequence -> Stub');

        (new Sequence(new Text('a')))->spacing(new Stub())->freeze();
    }

    public function testReferencesExposeResolvedTargetsWithoutUnrelatedRules(): void
    {
        $root = new Text('a');
        $rules = new Ruleset();
        $rules->define('word', $root)->define('unused', new Stub())->root('word');

        $this->assertSame([$rules->resolveRule(Ruleset::ROOT)], $rules->children());
        $reference = $rules->ref('word');
        $this->assertSame([$reference->graphTarget()], $reference->children());
        $implementation = new Implementation(new Definition($root));
        $this->assertSame([$root], $implementation->children());
        $this->assertSame([], $rules->freeze()->analysisWarnings);
    }

    public function testCustomCollectionCanExposeDependenciesWithoutAnalyzerChanges(): void
    {
        $parser = new class ('a') extends Text {
            private ArrayObject $operands;

            public function __construct(string $text)
            {
                parent::__construct($text);
                $this->operands = new ArrayObject([new Stub()]);
            }

            public function children(): array
            {
                return $this->operands->getArrayCopy();
            }
        };
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unresolved stub:');

        (new GraphAnalysis())->validate($parser);
    }

    public function testStubChildrenTrackBindingAndCycles(): void
    {
        $stub = new Stub();
        $this->assertSame([], $stub->children());
        $stub->setParser($stub);
        $this->assertSame([$stub], $stub->children());
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Left-recursive grammar:');

        (new GraphAnalysis())->validate($stub);
    }
}
