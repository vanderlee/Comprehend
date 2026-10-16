<?php

declare(strict_types=1);

namespace Tests\Feature\authoring;

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

abstract class AnalysisContractTest extends TestCase
{
    protected function forwarding(Parser $child): Parser
    {
        return new class ($child) extends Parser implements AnalyzableParser {
            use ForwardingAnalysisTrait;

            protected Parser $child;

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

uses(AnalysisContractTest::class);

test('custom contract participates in freeze and recognition', function (): void {
    $grammar = $this->forwarding(new Text('a'))->freeze();

    $this->assertSame([], $grammar->analysisWarnings);
    $this->assertTrue($grammar->parse('a')->success);
    $this->assertFalse($grammar->parse('b')->success);
});

test('custom nullable operand is rejected', function (): void {
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Nullable repetition:');

    Repeat::star($this->forwarding(new Nothing()))->freeze();
});

test('custom leading edges detect recursion', function (): void {
    $stub = new Stub();
    $parser = $this->forwarding($stub);
    $stub->setParser($parser);
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Left-recursive grammar:');

    $parser->freeze();
});

test('custom consuming recursion remains valid', function (): void {
    $stub = new Stub();
    $parser = $this->forwarding(new Sequence(new Text('a'), Repeat::optional($stub)));
    $stub->setParser($parser);

    $this->assertTrue($parser->freeze()->parse('aaa')->success);
});

test('opaque parser retains warning and runtime progress guard', function (): void {
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
});

test('custom progress requirement is checked', function (): void {
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
});

test('non monotone custom nullability is rejected', function (): void {
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
});

test('unknown custom nullability propagates without rejection', function (): void {
    $parser = new class ('a') extends Text {
        public function analysisNullability(NullabilityLookup $lookup): ?bool
        {
            return null;
        }
    };
    $grammar = Repeat::star($this->forwarding($parser))->freeze();

    $this->assertSame([], $grammar->analysisWarnings);
    $this->assertTrue($grammar->parse('aa')->success);
});

test('custom structural error includes graph path', function (): void {
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
});

test('undiscovered leading operand is rejected', function (): void {
    $parser = new class ('a') extends Text {
        public function analysisLeadingParsers(NullabilityLookup $lookup): array
        {
            return [new Text('b')];
        }
    };
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('outside the collected graph');

    $parser->freeze();
});

test('nullable custom prefix exposes following recursion', function (): void {
    $stub = new Stub();
    $parser = new Sequence($this->forwarding(new Nothing()), $stub);
    $stub->setParser($parser);
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Left-recursive grammar:');

    $parser->freeze();
});

test('validation does not retain previous graph', function (): void {
    $analysis = new GraphAnalysis();
    $this->assertSame([], $analysis->validate(new Nothing()));
    $this->assertSame([], $analysis->validate(new Text('a')));
});

test('unresolved stub still reports its path', function (): void {
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Unresolved stub: Sequence -> Stub');

    (new Sequence(new Stub()))->freeze();
});
