<?php

declare(strict_types=1);

namespace Tests\Src\Builder;

use LogicException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\GraphAnalysis;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Text;

final class GraphAnalysisTest extends TestCase
{
    public function testConsumingRepetitionNeedsNoWarnings(): void
    {
        $this->assertSame([], (new GraphAnalysis())->validate(Repeat::star(new Text('a'))));
    }

    public function testNullableRepetitionIsRejectedBeforeRecognition(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Nullable repetition:');

        (new GraphAnalysis())->validate(Repeat::star(new Nothing()));
    }
}
