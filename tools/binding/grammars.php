<?php

declare(strict_types=1);

namespace ComprehendComparison;

use Examples\ApplicationValues\Job;
use Parsica\Parsica\Parser as BindingParser;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Binding;

use function Parsica\Parsica\between;
use function Parsica\Parsica\collect;
use function Parsica\Parsica\digitChar;
use function Parsica\Parsica\either;
use function Parsica\Parsica\fail;
use function Parsica\Parsica\pure;
use function Parsica\Parsica\satisfy;
use function Parsica\Parsica\sepBy;
use function Parsica\Parsica\string;
use function Parsica\Parsica\takeWhile;
use function Parsica\Parsica\takeWhile1;

/** @return C<Job> */
function comprehendJob(): C
{
    $name = Binding::syntax()->regex('/[A-Za-z][A-Za-z0-9_]*/A');
    $number = Binding::syntax()->regex('/(?:0|[1-9][0-9]{0,2})/A')->mapText('intval');
    $retryValue = Binding::syntax()->valueChoice(
        Binding::syntax()->range('0', '9')->mapText('intval'),
        Binding::syntax()->text('off')->map(static fn(string $text): bool => false),
    );
    $retries = Binding::syntax()->text('retry=')->ignoreThen($retryValue)->optionalValue(default: 3);
    $batch = $number->separatedBy(',')->between('[', ']');
    $batches = $batch->separatedBy(',')->between('[', ']');

    return Binding::syntax()->collect($name, $retries, $batches)
        ->map(static fn(array $parts): Job => new Job($parts[0], $parts[1], $parts[2]))
        ->spacing(Binding::syntax()->set(" \t\r\n")->many());
}

/** @return BindingParser<Job> */
function bindingJob(): BindingParser
{
    $space = takeWhile(static fn(string $c): bool => str_contains(" \t\r\n", $c));
    $symbol = static fn(string $text): BindingParser => $space->followedBy(string($text));
    $name = collect(
        satisfy(static fn(string $c): bool => preg_match('/[A-Za-z]/', $c) === 1),
        takeWhile(static fn(string $c): bool => preg_match('/[A-Za-z0-9_]/', $c) === 1),
    )->map(static fn(array $parts): string => implode('', $parts));
    $number = takeWhile1(static fn(string $c): bool => str_contains('0123456789', $c))
        ->bind(static fn(string $text): BindingParser => preg_match('/\A(?:0|[1-9][0-9]{0,2})\z/', $text) === 1
            ? pure((int) $text) : fail('integer from 0 to 999 without leading zeros'));
    $retryValue = either(
        digitChar()->map('intval'),
        string('off')->map(static fn(string $text): bool => false),
    );
    $retries = either($symbol('retry=')->followedBy($space)->followedBy($retryValue), pure(3));
    $batch = between($symbol('['), $symbol(']'), sepBy($symbol(','), $space->followedBy($number)));
    $batches = between($symbol('['), $symbol(']'), sepBy($symbol(','), $batch));

    return collect($name, $retries, $batches)
        ->map(static fn(array $parts): Job => new Job($parts[0], $parts[1], $parts[2]))
        ->thenEof();
}
