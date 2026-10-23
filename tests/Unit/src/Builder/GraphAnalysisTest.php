<?php

declare(strict_types=1);

namespace Tests\Src\Builder;

use LogicException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\GraphAnalysis;
use Vanderlee\Comprehend\Builder\NullabilityLookup;
use Vanderlee\Comprehend\Directive;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\All;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Except;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Stub;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ChoicePolicy;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

uses(TestCase::class);

test('consuming repetition needs no warnings', function (): void {
    $this->assertSame([], (new GraphAnalysis())->validate(Repeat::star(new Text('a'))));
});

test('nullability calculations read stable estimates throughout each round', function (): void {
    $parser = new class (new Nothing(), new Nothing()) extends Sequence {
        /** @var list<list<bool|null>> */
        public array $observed = [];

        public function analysisNullability(NullabilityLookup $lookup): ?bool
        {
            $this->observed[] = array_map($lookup->get(...), $this->children());

            return parent::analysisNullability($lookup);
        }
    };

    $this->assertSame([], (new GraphAnalysis())->validate($parser));
    $this->assertSame([false, false], $parser->observed[0]);
    $this->assertContains([true, true], $parser->observed);
    foreach ($parser->observed as $estimates) {
        $this->assertSame($estimates[0], $estimates[1]);
    }
});

test('analysis can be reused after a failed graph without stale estimates', function (): void {
    $analysis = new GraphAnalysis();
    expect(static fn() => $analysis->validate(Repeat::plus(new Nothing())))
        ->toThrow(LogicException::class, 'Nullable repetition:');
    $this->assertSame([], $analysis->validate(Repeat::plus(new Text('a'))));
});

test('nullable repetition is rejected before recognition', function (): void {
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Nullable repetition:');

    (new GraphAnalysis())->validate(Repeat::star(new Nothing()));
});

test('zero maximum does not follow recursive operand', function (): void {
    $stub = new Stub();
    $repeat = new Repeat($stub, 0, 0);
    $stub->setParser($repeat);

    $this->assertSame([], (new GraphAnalysis())->validate($repeat));
});

test('nullable wrapped operands are rejected', function (): void {
    $empty = new Nothing();
    $wrappers = [
        new Directive\CaseSensitive(true, $empty),
        new Directive\Prefer(ChoicePolicy::FIRST, $empty),
        new Directive\Space(new Text(' '), $empty),
        new Directive\Lexeme($empty),
        new Directive\Trivia($empty),
        (new Stub())->setParser($empty),
        new All($empty, new Text('a')),
        new Except($empty, new Text('a')),
    ];

    foreach ($wrappers as $wrapper) {
        try {
            (new GraphAnalysis())->validate(Repeat::star($wrapper));
            $this->fail('Expected nullable repetition for ' . $wrapper::class);
        } catch (LogicException $exception) {
            $this->assertStringContainsString('Nullable repetition:', $exception->getMessage());
        }
    }
});

test('except nullability uses positive operand only', function (): void {
    $parser = Repeat::plus(new Except(new Text('a'), new Nothing()));

    $this->assertSame([], (new GraphAnalysis())->validate($parser));
});

test('except excluded operand participates in leading cycle checks', function (): void {
    $stub = new Stub();
    $parser = new Except(new Text('a'), $stub);
    $stub->setParser($parser);

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Left-recursive grammar:');

    (new GraphAnalysis())->validate($parser);
});

test('shared opaque nodes retain first discovery paths and warning order', function (): void {
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
    $first = (clone $opaque)->diagnosticRule('first');
    $second = (clone $opaque)->diagnosticRule('second');
    $left = (new Sequence($first, $second))->diagnosticRule('left');
    $right = (new Sequence($second, $first))->diagnosticRule('right');
    $root = (new Choice($left, $right))->diagnosticRule('root');

    $this->assertSame([
        'Opaque parser: root -> left -> first',
        'Opaque parser: root -> left -> second',
    ], (new GraphAnalysis())->validate($root));
});

test('deferred error paths use collection labels without calling parsers again', function (): void {
    $parser = new class ('a') extends Text {
        public function analysisNullability(NullabilityLookup $lookup): ?bool
        {
            $this->diagnosticRule('changed during analysis');

            return !$lookup->get($this);
        }
    };
    $parser->diagnosticRule('original');
    $branch = (new Sequence($parser))->diagnosticRule('branch');
    $root = (new Sequence($branch))->diagnosticRule('root');

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Non-monotone nullability: root -> branch -> original');
    (new GraphAnalysis())->validate($root);
});

test('structural and progress errors retain complete ancestry', function (): void {
    $analysis = new GraphAnalysis();
    $missing = (new Stub())->diagnosticRule('missing');
    $branch = (new Sequence($missing))->diagnosticRule('branch');
    $root = (new Sequence($branch))->diagnosticRule('root');
    try {
        $analysis->validate($root);
        $this->fail('Expected an unresolved stub');
    } catch (LogicException $exception) {
        $this->assertSame('Unresolved stub: root -> branch -> missing', $exception->getMessage());
    }

    $repeat = Repeat::plus((new Nothing())->diagnosticRule('empty'))->diagnosticRule('repeat');
    $root = (new Sequence((new Sequence($repeat))->diagnosticRule('branch')))->diagnosticRule('root');
    try {
        $analysis->validate($root);
        $this->fail('Expected nullable repetition');
    } catch (LogicException $exception) {
        $this->assertSame('Nullable repetition: root -> branch -> repeat -> empty', $exception->getMessage());
    }
});

test('completed siblings leave no cycle ancestry and failed analysis can be retried', function (): void {
    $empty = (new Nothing())->diagnosticRule('shared empty');
    $loop = (new Stub())->diagnosticRule('loop');
    $production = (new Sequence($empty, $loop))->diagnosticRule('production');
    $loop->setParser($production);
    $root = (new Choice($empty, $loop))->diagnosticRule('root');
    $analysis = new GraphAnalysis();
    for ($attempt = 0; $attempt < 2; ++$attempt) {
        try {
            $analysis->validate($root);
            $this->fail('Expected a leading cycle');
        } catch (LogicException $exception) {
            $this->assertSame(
                'Left-recursive grammar: root -> loop -> production -> loop',
                $exception->getMessage(),
            );
        }
    }

    $loop->setParser(new Text('a'));
    $this->assertSame([], $analysis->validate($root));
});
