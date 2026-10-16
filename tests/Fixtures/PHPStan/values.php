<?php

declare(strict_types=1);

namespace Tests\Fixtures\PHPStan;

use DateTimeImmutable;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Binding;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;

use function Examples\ApplicationValues\jobParser;
use function PHPStan\Testing\assertType;

function inferredValues(): void
{
    $number = Binding::syntax()->integer()->mapText('intval');
    assertType('Vanderlee\Comprehend\Comprehend<int>', $number);
    assertType('Vanderlee\Comprehend\Comprehend<int>', $number->map(fn($n) => $n + 1));
    assertType('Vanderlee\Comprehend\Comprehend<lowercase-string&numeric-string>', $number->map(
        fn(int $n): string => sprintf('%d', $n),
    ));
    assertType('Vanderlee\Comprehend\Comprehend<DateTimeImmutable>', Binding::syntax()->text('now')->mapText(
        static fn(string $text): DateTimeImmutable => new DateTimeImmutable($text),
    ));
    assertType('Vanderlee\Comprehend\Comprehend<int<0, max>>', $number->mapValue(
        static fn(ParseResult $result): int => strlen($result->rawText()),
    ));
    assertType('Vanderlee\Comprehend\Comprehend<list<int>>', $number->manyValues());
    assertType('Vanderlee\Comprehend\Comprehend<non-empty-list<int>>', $number->someValues());
    assertType('Vanderlee\Comprehend\Comprehend<list<int>>', Binding::syntax()->repeatValues(parser: $number, min: 2));
    assertType('Vanderlee\Comprehend\Comprehend<list<int>>', Binding::syntax()->manyValues($number));
    assertType('Vanderlee\Comprehend\Comprehend<non-empty-list<int>>', Binding::syntax()->someValues($number));
    assertType('Vanderlee\Comprehend\Comprehend<list<int>>', Binding::syntax()->manyValues($number));
    assertType('Vanderlee\Comprehend\Comprehend<int>', Binding::syntax()->integer()->mapText('intval'));
    assertType('Vanderlee\Comprehend\Comprehend<list<int>>', $number->separatedBy(','));
    assertType('Vanderlee\Comprehend\Comprehend<list<int>>', Binding::syntax()->separatedBy(
        item: $number,
        separator: ',',
    ));
    assertType('Vanderlee\Comprehend\Comprehend<list<list<int>>>', $number->manyValues()->manyValues());
    assertType('Vanderlee\Comprehend\Comprehend<int|null>', $number->optionalValue());
    assertType('Vanderlee\Comprehend\Comprehend<int|false>', $number->optionalValue(default: false));
    assertType('Vanderlee\Comprehend\Comprehend<int|false>', Binding::syntax()->optionalValue(
        default: false,
        parser: $number,
    ));
    assertType('Vanderlee\Comprehend\Comprehend<int|null>', Binding::syntax()->optionalValue($number));
    assertType('Vanderlee\Comprehend\Comprehend<int>', Binding::syntax()->text('n=')->ignoreThen($number));
    assertType('Vanderlee\Comprehend\Comprehend<int>', $number->between('(', ')')->thenIgnore(';')->named('number'));
    assertType('Vanderlee\Comprehend\Comprehend<int>', $number->spacing(
        Binding::syntax()->text(' ')->many(),
    )->lexeme());
    assertType('Vanderlee\Comprehend\Comprehend<int>', Binding::syntax()->of($number));
    assertType('Vanderlee\Comprehend\Comprehend<mixed>', Binding::syntax()->of(new Text('x')));
    assertType('Vanderlee\Comprehend\Comprehend<array{string, int}>', Binding::syntax()->collect('a', $number));
    assertType('Vanderlee\Comprehend\Comprehend<int|string>', Binding::syntax()->valueChoice($number, 'nil'));
    assertType('Vanderlee\Comprehend\Builder\FrozenGrammar<int>', $number->freeze());
    assertType('Vanderlee\Comprehend\Result\ParseResult<int>', $number->parse('1'));
    assertType('int|null', $number->parseAll('1')->value());
    assertType('int|null', $number->matchPrefix('1x')->output());
    assertType('int|null', $number->freeze()->parseAll('1')->value());
    assertType('int|null', $number->freeze()->matchPrefix('1x')->output());
    assertType('Vanderlee\Comprehend\Comprehend<Examples\ApplicationValues\Job>', jobParser());
    assertType('Examples\ApplicationValues\Job|null', jobParser()->freeze()->parse('report []')->value());
}

/** @param list<C<int>> $items */
function unpacked(array $items): void
{
    assertType('Vanderlee\Comprehend\Comprehend<mixed>', Binding::syntax()->collect(...$items));
}
