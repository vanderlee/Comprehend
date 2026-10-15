<?php

declare(strict_types=1);

namespace Tests\Src\Source;

use PHPUnit\Framework\TestCase;
use ValueError;
use Vanderlee\Comprehend\Source\SourceMode;

final class SourceModeTest extends TestCase
{
    public function testSerializedModesRoundTripWithoutImplicitFallback(): void
    {
        $this->assertSame(SourceMode::BYTES, SourceMode::from('bytes'));
        $this->assertSame(SourceMode::UTF8, SourceMode::from('utf8'));
        $this->assertNull(SourceMode::tryFrom('unknown'));
        $this->expectException(ValueError::class);

        SourceMode::from('unknown');
    }
}
