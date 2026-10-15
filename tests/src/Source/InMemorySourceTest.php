<?php

declare(strict_types=1);

namespace Tests\Src\Source;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Source\InMemorySource;
use Vanderlee\Comprehend\Source\SourceMode;
use Vanderlee\Comprehend\Source\Span;

final class InMemorySourceTest extends TestCase
{
    public function testByteSlicesAndSpansUseHalfOpenIntervals(): void
    {
        $source = $this->source();

        $this->assertSame(3, $source->byteLength());
        $this->assertSame('bc', $source->sliceBytes(1, 3));
        $this->assertSame('', $source->sliceBytes(3, 3));
        $this->assertSame($source, $source->span(0, 1)->source);
        $this->assertSame('a', $source->span(0, 1)->rawText());
    }

    /** @dataProvider invalidRanges */
    public function testInvalidSlicesAreRejected(int $start, int $end): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->source()->sliceBytes($start, $end);
    }

    public static function invalidRanges(): array
    {
        return [[-1, 1], [0, 4], [2, 1]];
    }

    private function source(): InMemorySource
    {
        return new class ('abc') extends InMemorySource {
            public function mode(): SourceMode
            {
                return SourceMode::BYTES;
            }

            public function readUnit(int $byteOffset): ?Span
            {
                $this->assertByteOffset($byteOffset);

                return $byteOffset === $this->byteLength() ? null : $this->span($byteOffset, $byteOffset + 1);
            }

            public function isUnitBoundary(int $byteOffset): bool
            {
                $this->assertByteOffset($byteOffset);

                return true;
            }
        };
    }
}
