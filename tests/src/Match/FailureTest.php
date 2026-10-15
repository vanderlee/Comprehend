<?php

declare(strict_types=1);

namespace Tests\Src\Match;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Match\Failure;
use Vanderlee\Comprehend\Source\ByteSource;

uses(TestCase::class);

test('failure keeps progress separate from its empty span', function (): void {
    $source = new ByteSource('abc');
    $failure = new Failure(2);
    $failure->setSourceMetadata($source->span(0, 0), 2);

    $this->assertFalse($failure->accepted);
    $this->assertSame(2, $failure->length);
    $this->assertSame(0, $failure->span->byteLength());
    $this->assertSame(2, $failure->farthestFailure);
});

test('nonempty failure spans are rejected', function (): void {
    $this->expectException(InvalidArgumentException::class);

    (new Failure())->setSourceMetadata((new ByteSource('a'))->span(0, 1), null);
});
