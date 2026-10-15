<?php

declare(strict_types=1);

namespace Tests\Src\Builder;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\ForwardingAnalysisTrait;
use Vanderlee\Comprehend\Builder\NullabilityLookup;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Terminal\Text;

final class ForwardingAnalysisTraitTest extends TestCase
{
    public function testForwardsEachEstimateAndTheLeadingOperand(): void
    {
        $child = new Text('a');
        $analysis = new class ($child) {
            use ForwardingAnalysisTrait;

            private Parser $child;

            public function __construct(Parser $child)
            {
                $this->child = $child;
            }

            protected function analysisTarget(): Parser
            {
                return $this->child;
            }
        };

        foreach ([false, null, true] as $value) {
            $lookup = new NullabilityLookup([spl_object_id($child) => $value]);
            $this->assertSame($value, $analysis->analysisNullability($lookup));
            $this->assertSame([$child], $analysis->analysisLeadingParsers($lookup));
        }
        $this->assertSame([], $analysis->analysisProgressParsers());
        $this->assertNull($analysis->analysisError());
    }
}
