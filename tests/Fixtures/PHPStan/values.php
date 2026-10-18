<?php

declare(strict_types=1);

namespace Tests\Fixtures\PHPStan;

use DateTimeImmutable;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Extraction\Extraction;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;

use function Examples\ApplicationValues\jobParser;
use function PHPStan\Testing\assertType;

function inferredValues(): void
{
    $record = Extraction::syntax()->record([
        'name' => Extraction::syntax()->text('name'),
        'count' => Extraction::syntax()->integer()->mapText('intval'),
        'enabled' => Extraction::syntax()->text('off')->map(static fn(string $text): bool => false),
    ]);
    assertType('Vanderlee\Comprehend\Comprehend<array<mixed>>', $record);
    assertType('Vanderlee\Comprehend\Comprehend<array<mixed>>', Extraction::syntax()->record([]));
    assertType('Vanderlee\Comprehend\Comprehend<array<mixed>>', Extraction::syntax()->record(
        fields: ['field' => 'x'],
    ));
    assertType(
        'Vanderlee\Comprehend\Comprehend<array<mixed>>',
        Extraction::syntax()->record(['outer' => $record]),
    );
    assertType('Vanderlee\Comprehend\Comprehend<string>', Extraction::syntax()->text('x')->record(['field' => 'y']));
    assertType('Vanderlee\Comprehend\Comprehend<string>', C::readWhile(static fn(string $c): bool => $c !== ';'));
    assertType('Vanderlee\Comprehend\Comprehend<string>', C::readUntil(';', min: 1));
    assertType('Vanderlee\Comprehend\Comprehend<string>', C::syntax()->readUntil(
        static fn(string $c): bool => $c === ';',
    ));
    assertType('Vanderlee\Comprehend\Comprehend<string>', C::readWhileSet('abc'));
    assertType('Vanderlee\Comprehend\Comprehend<string>', C::syntax()->readWhileRange('0', '9'));
    assertType('Vanderlee\Comprehend\Comprehend<string>', C::readRest());
    assertType('Vanderlee\Comprehend\Comprehend<null>', C::skipWhile(static fn(string $c): bool => $c === ' '));
    assertType('Vanderlee\Comprehend\Comprehend<null>', C::syntax()->skipUntil(';'));
    assertType('Vanderlee\Comprehend\Comprehend<null>', C::skipRest());
    assertType('string|null', C::readUntil(';')->matchPrefix('abc;')->value());
    $number = Extraction::syntax()->integer()->mapText('intval');
    assertType('Vanderlee\Comprehend\Comprehend<int>', $number);
    assertType('Vanderlee\Comprehend\Comprehend<int>', $number->mapWith(
        next: $number,
        combine: static fn(int $a, int $b): int => $a + $b,
    ));
    assertType('Vanderlee\Comprehend\Comprehend<DateTimeImmutable>', $number->mapWith(
        $number,
        static fn(int $a, int $b): DateTimeImmutable => new DateTimeImmutable('now'),
    ));
    assertType('Vanderlee\Comprehend\Comprehend<int>', $number->foldLeft(
        step: $number,
        combine: static fn(int $a, int $b): int => $a - $b,
        min: 1,
        max: 3,
    ));
    assertType('int|null', $number->foldLeft($number, static fn(int $a, int $b): int => $a + $b)
        ->parse('12')->value());
    assertType('Vanderlee\Comprehend\Comprehend<int>', $number->map(fn($n) => $n + 1));
    assertType('Vanderlee\Comprehend\Comprehend<lowercase-string&numeric-string>', $number->map(
        fn(int $n): string => sprintf('%d', $n),
    ));
    assertType('Vanderlee\Comprehend\Comprehend<DateTimeImmutable>', Extraction::syntax()->text('now')->mapText(
        static fn(string $text): DateTimeImmutable => new DateTimeImmutable($text),
    ));
    assertType('Vanderlee\Comprehend\Comprehend<int<0, max>>', $number->mapValue(
        static fn(ParseResult $result): int => strlen($result->rawText()),
    ));
    assertType('Vanderlee\Comprehend\Comprehend<list<int>>', $number->manyValues());
    assertType('Vanderlee\Comprehend\Comprehend<non-empty-list<int>>', $number->someValues());
    assertType('Vanderlee\Comprehend\Comprehend<list<mixed>>', Extraction::syntax()->repeatValues(
        parser: $number,
        min: 2,
    ));
    assertType('Vanderlee\Comprehend\Comprehend<list<mixed>>', Extraction::syntax()->manyValues($number));
    assertType('Vanderlee\Comprehend\Comprehend<list<mixed>>', Extraction::syntax()->someValues($number));
    assertType('Vanderlee\Comprehend\Comprehend<list<mixed>>', Extraction::syntax()->manyValues($number));
    assertType('Vanderlee\Comprehend\Comprehend<int>', Extraction::syntax()->integer()->mapText('intval'));
    assertType('Vanderlee\Comprehend\Comprehend<list<mixed>>', $number->separatedBy(','));
    assertType('Vanderlee\Comprehend\Comprehend<list<mixed>>', Extraction::syntax()->separatedBy(
        item: $number,
        separator: ',',
    ));
    assertType('Vanderlee\Comprehend\Comprehend<list<list<int>>>', $number->manyValues()->manyValues());
    assertType('Vanderlee\Comprehend\Comprehend<int|null>', $number->optionalValue());
    assertType('Vanderlee\Comprehend\Comprehend<bool|int>', $number->optionalValue(default: false));
    assertType('Vanderlee\Comprehend\Comprehend<mixed>', Extraction::syntax()->optionalValue(
        default: false,
        parser: $number,
    ));
    assertType('Vanderlee\Comprehend\Comprehend<mixed>', Extraction::syntax()->optionalValue($number));
    assertType('Vanderlee\Comprehend\Comprehend<mixed>', Extraction::syntax()->text('n=')->ignoreThen($number));
    assertType('Vanderlee\Comprehend\Comprehend<int>', $number->between('(', ')')->thenIgnore(';')->named('number'));
    assertType('Vanderlee\Comprehend\Comprehend<int>', $number->spacing(
        Extraction::syntax()->text(' ')->many(),
    )->lexeme());
    assertType('Vanderlee\Comprehend\Comprehend<int>', Extraction::syntax()->of($number));
    assertType('Vanderlee\Comprehend\Comprehend<mixed>', Extraction::syntax()->of(new Text('x')));
    assertType('Vanderlee\Comprehend\Comprehend<list<mixed>>', Extraction::syntax()->collect('a', $number));
    assertType('Vanderlee\Comprehend\Comprehend<mixed>', Extraction::syntax()->valueChoice($number, 'nil'));
    assertType('Vanderlee\Comprehend\Builder\FrozenGrammar<int>', $number->freeze());
    assertType('Vanderlee\Comprehend\Result\ParseResult<int>', $number->parse('1'));
    assertType('int|null', $number->parseAll('1')->value());
    assertType('int|null', $number->matchPrefix('1x')->output());
    assertType('int|null', $number->freeze()->parseAll('1')->value());
    assertType('int|null', $number->freeze()->matchPrefix('1x')->output());
    assertType('Vanderlee\Comprehend\Comprehend<Examples\ApplicationValues\Job>', jobParser());
    assertType('Examples\ApplicationValues\Job|null', jobParser()->freeze()->parse('report []')->value());
}

/** @param array<string, C<int>> $fields */
function inferredDynamicRecord(array $fields): void
{
    assertType('Vanderlee\Comprehend\Comprehend<array<mixed>>', Extraction::syntax()->record($fields));
}

/** @param array{maybe?: C<int>} $fields */
function inferredOptionalRecord(array $fields): void
{
    assertType('Vanderlee\Comprehend\Comprehend<array<mixed>>', Extraction::syntax()->record($fields));
}

/** @param list<C<int>> $items */
function unpacked(array $items): void
{
    assertType('Vanderlee\Comprehend\Comprehend<list<mixed>>', Extraction::syntax()->collect(...$items));
}
