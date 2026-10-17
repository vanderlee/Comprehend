<?php

declare(strict_types=1);

namespace Tests\Fixtures\PHPStan;

use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ChoicePolicy;

use function PHPStan\Testing\assertType;

function coreStructures(): void
{
    $number = C::integer()->mapValue(static fn(ParseResult $result): int => (int) $result->rawText());
    assertType('Vanderlee\Comprehend\Comprehend<list<int>>', $number->separatedBy(','));
    assertType('Vanderlee\Comprehend\Comprehend<list<int>>', C::separatedBy(item: $number, separator: ','));
    assertType('Vanderlee\Comprehend\Comprehend<list<int>>', C::syntax()->separatedBy($number, ','));
    assertType('Vanderlee\Comprehend\Comprehend<int>', C::text('n=')->ignoreThen(next: $number));
    assertType('Vanderlee\Comprehend\Comprehend<int>', $number->thenIgnore(';')->between('(', ')'));
    assertType('Vanderlee\Comprehend\Comprehend<int>', $number->named('number')->tag('numeric')
        ->diagnosticRule('number')->caseSensitive()->prefer(ChoicePolicy::FIRST)
        ->guard(static fn(): bool => true)->validateSemantic(static fn(): bool => true)
        ->onCommit(static fn() => null)->setResult('number')->concatResult('text')->pushResult('items')
        ->spacing(null)->lexeme()->trivia());
    assertType('Vanderlee\Comprehend\Result\ParseResult<int>', $number->parseAll(
        '42',
        spacing: C::text(' ')->many(),
    ));
    assertType('Vanderlee\Comprehend\Result\ParseResult<int>', $number->matchPrefix(
        '42!',
        spacing: C::text(' ')->many(),
    ));
    assertType('Vanderlee\Comprehend\Comprehend<list<string>>', C::separatedBy('a', ','));
}
