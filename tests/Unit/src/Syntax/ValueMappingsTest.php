<?php

declare(strict_types=1);

namespace Tests\Unit\src\Syntax;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Result\ParseResult;

uses(TestCase::class);

test('analysis mapping signatures correspond to the existing lazy magic operations', function (): void {
    $number = C::integer()->mapText('intval');
    $this->assertSame(2, $number->map(static fn(int $value): int => $value + 1)->parse('1')->value());
    $this->assertSame('1', $number->mapValue(static fn(ParseResult $r): string => $r->rawText())->parse('1')->value());
    $this->assertSame(1, $number->parse('1')->value());
});
