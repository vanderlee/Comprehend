<?php

declare(strict_types=1);

namespace Examples\ApplicationValues;

use Vanderlee\Comprehend\Comprehend as C;

final class Job
{
    public readonly string $name;
    public readonly int|bool $retries;
    /** @var list<list<int>> */
    public readonly array $batches;

    /** @param list<list<int>> $batches */
    public function __construct(string $name, int|bool $retries, array $batches)
    {
        $this->name = $name;
        $this->retries = $retries;
        $this->batches = $batches;
    }
}

/** @return C<Job> */
function jobParser(): C
{
    $name = C::regex('/[A-Za-z][A-Za-z0-9_]*/A');
    $number = C::integer(0, 999)->mapText('intval');
    $retryValue = C::valueChoice(
        C::integer(0, 9)->mapText('intval'),
        C::text('off')->map(static fn(string $text): bool => false),
    );
    $retries = C::text('retry=')->ignoreThen($retryValue)->optionalValue(default: 3);
    $batch = $number->separatedBy(',')->between('[', ']');
    $batches = $batch->separatedBy(',')->between('[', ']');

    return C::collect($name, $retries, $batches)
        ->map(static fn(array $parts): Job => new Job($parts[0], $parts[1], $parts[2]))
        ->spacing(C::set(" \t\r\n")->many());
}
