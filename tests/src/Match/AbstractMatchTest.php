<?php

declare(strict_types=1);

namespace Tests\Src\Match;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Match\AbstractMatch;

uses(TestCase::class);

test('base record has no acceptance or semantic results', function (): void {
    $match = $this->getMockForAbstractClass(AbstractMatch::class, [2]);

    $this->assertFalse($match->accepted);
    $this->assertSame(2, $match->length);
    $this->assertSame([], $match->getResults());
    $this->assertSame('fallback', $match->getResult('missing', 'fallback'));
});

test('negative progress is rejected', function (): void {
    $this->expectException(InvalidArgumentException::class);

    $this->getMockForAbstractClass(AbstractMatch::class, [-1]);
});
