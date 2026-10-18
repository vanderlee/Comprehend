<?php

declare(strict_types=1);

namespace Tests\Src\Builder;

use InvalidArgumentException;
use Vanderlee\Comprehend\Builder\FreezeFlags;

test('freeze flags accept supported combinations', function (int $flags) {
    FreezeFlags::validate($flags);
    expect($flags & ~FreezeFlags::OPTIMIZE)->toBe(0);
})->with([FreezeFlags::NONE, FreezeFlags::OPTIMIZE, FreezeFlags::DEFAULT]);

test('freeze flags reject unknown bits including negative masks', function (int $flags) {
    expect(static fn() => FreezeFlags::validate($flags))
        ->toThrow(InvalidArgumentException::class, 'Unknown freeze flags');
})->with([2, 3, -1, PHP_INT_MIN, PHP_INT_MAX]);
