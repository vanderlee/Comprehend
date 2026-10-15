<?php

declare(strict_types=1);

namespace Tests\features\authoring;

use LogicException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\AnalyzableParser;
use Vanderlee\Comprehend\Builder\ForwardingAnalysisTrait;
use Vanderlee\Comprehend\Builder\GraphAnalysis;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Builder\NullabilityLookup;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Stub;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

final class AnalysisContractTest extends TestCase
{
    public function testCustomContractParticipatesInFreezeAndRecognition(): void
    {
        $grammar = $this->forwarding(new Text('a'))->freeze();

        $this->assertSame([], $grammar->analysisWarnings);
        $this->assertTrue($grammar->parse('a')->success);
        $this->assertFalse($grammar->parse('b')->success);
    }

    public function testCustomNullableOperandIsRejected(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Nullable repetition:');

        Repeat::star($this->forwarding(new Nothing()))->freeze();
    }

    public function testCustomLeadingEdgesDetectRecursion(): void
    {
        $stub = new Stub();
        $parser = $this->forwarding($stub);
        $stub->setParser($parser);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Left-recursive grammar:');

        $parser->freeze();
    }

    public function testCustomConsumingRecursionRemainsValid(): void
    {
        $stub = new Stub();
        $parser = $this->forwarding(new Sequence(new Text('a'), Repeat::optional($stub)));
        $stub->setParser($parser);

        $this->assertTrue($parser->freeze()->parse('aaa')->success);
    }

    public function testOpaqueParserRetainsWarningAndRuntimeProgressGuard(): void
    {
        $opaque = new class extends Parser {
            public function __toString(): string
            {
                return 'opaque';
            }

            protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
            {
                return $this->success($source, $byteOffset);
            }
        };
        $grammar = Repeat::star($opaque)->freeze();
        $this->assertCount(1, $grammar->analysisWarnings);
        $this->assertStringContainsString('Opaque parser:', $grammar->analysisWarnings[0]);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('without consuming input');

        $grammar->parse('');
    }

    public function testCustomProgressRequirementIsChecked(): void
    {
        $parser = new class (new Nothing()) extends Text {
            public function __construct(private Parser $child)
            {
                parent::__construct('a');
            }

            public function analysisProgressParsers(): array
            {
                return [$this->child];
            }

            public function children(): array
            {
                return [$this->child];
            }

            protected function copyDependencies(GraphSnapshot $context): void
            {
                parent::copyDependencies($context);
                $this->child = $context->copy($this->child);
            }
        };
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Nullable repetition:');

        $parser->freeze();
    }

    public function testNonMonotoneCustomNullabilityIsRejected(): void
    {
        $parser = new class extends Text {
            public function __construct()
            {
                parent::__construct('a');
            }

            public function analysisNullability(NullabilityLookup $lookup): ?bool
            {
                return !$lookup->get($this);
            }
        };
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Non-monotone nullability:');

        $parser->freeze();
    }

    public function testUnknownCustomNullabilityPropagatesWithoutRejection(): void
    {
        $parser = new class ('a') extends Text {
            public function analysisNullability(NullabilityLookup $lookup): ?bool
            {
                return null;
            }
        };
        $grammar = Repeat::star($this->forwarding($parser))->freeze();

        $this->assertSame([], $grammar->analysisWarnings);
        $this->assertTrue($grammar->parse('aa')->success);
    }

    public function testCustomStructuralErrorIncludesGraphPath(): void
    {
        $parser = new class ('a') extends Text {
            public function graphLabel(): string
            {
                return 'custom';
            }

            public function analysisError(): ?string
            {
                return 'Missing configuration';
            }
        };
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Missing configuration: Sequence -> custom');

        (new Sequence($parser))->freeze();
    }

    public function testUndiscoveredLeadingOperandIsRejected(): void
    {
        $parser = new class ('a') extends Text {
            public function analysisLeadingParsers(NullabilityLookup $lookup): array
            {
                return [new Text('b')];
            }
        };
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('outside the collected graph');

        $parser->freeze();
    }

    public function testNullableCustomPrefixExposesFollowingRecursion(): void
    {
        $stub = new Stub();
        $parser = new Sequence($this->forwarding(new Nothing()), $stub);
        $stub->setParser($parser);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Left-recursive grammar:');

        $parser->freeze();
    }

    public function testValidationDoesNotRetainPreviousGraph(): void
    {
        $analysis = new GraphAnalysis();
        $this->assertSame([], $analysis->validate(new Nothing()));
        $this->assertSame([], $analysis->validate(new Text('a')));
    }

    public function testUnresolvedStubStillReportsItsPath(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unresolved stub: Sequence -> Stub');

        (new Sequence(new Stub()))->freeze();
    }

    private function forwarding(Parser $child): Parser
    {
        return new class ($child) extends Parser implements AnalyzableParser {
            use ForwardingAnalysisTrait;

            private Parser $child;

            public function __construct(Parser $child)
            {
                $this->child = $child;
            }

            protected function analysisTarget(): Parser
            {
                return $this->child;
            }

            protected function copyDependencies(GraphSnapshot $context): void
            {
                parent::copyDependencies($context);
                $this->child = $context->copy($this->child);
            }

            public function children(): array
            {
                return [$this->child];
            }

            public function __toString(): string
            {
                return 'custom wrapper';
            }

            protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
            {
                return $this->child->recognizeInSession($source, $byteOffset, $session);
            }
        };
    }
}
