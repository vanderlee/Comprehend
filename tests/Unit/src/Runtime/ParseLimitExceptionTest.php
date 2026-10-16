<?php

declare(strict_types=1);

namespace Tests\Src\Runtime;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Runtime\ParseLimitException;

uses(TestCase::class);

test('budget details are available as structured data and message', function (): void {
    $error = new ParseLimitException('depth', 4, ['root', 'item'], 3);

    $this->assertSame('depth', $error->limit);
    $this->assertSame(4, $error->byteOffset);
    $this->assertSame(['root', 'item'], $error->rulePath);
    $this->assertSame(3, $error->maximum);
    $this->assertSame('Parse depth limit 3 exceeded at byte 4: root -> item', $error->getMessage());
});
