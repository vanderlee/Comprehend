<?php

declare(strict_types=1);

namespace Tests\Src\Builder;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\DefaultAnalysisTrait;
use Vanderlee\Comprehend\Builder\NullabilityLookup;

uses(TestCase::class);

test('defaults declare no edges or structural errors', function (): void {
    $analysis = new class {
        use DefaultAnalysisTrait;
    };

    $this->assertSame([], $analysis->analysisLeadingParsers(new NullabilityLookup([])));
    $this->assertSame([], $analysis->analysisProgressParsers());
    $this->assertNull($analysis->analysisError());
});
