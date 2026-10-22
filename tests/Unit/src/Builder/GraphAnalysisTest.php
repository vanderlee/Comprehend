<?php

declare(strict_types=1);

namespace Tests\Src\Builder;

use LogicException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\GraphAnalysis;
use Vanderlee\Comprehend\Builder\NullabilityLookup;
use Vanderlee\Comprehend\Directive;
use Vanderlee\Comprehend\Parser\Structure\All;
use Vanderlee\Comprehend\Parser\Structure\Except;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Stub;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ChoicePolicy;

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
