<?php

declare(strict_types=1);

namespace Tests\Src\Match;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Match\AbstractMatch;

final class AbstractMatchTest extends TestCase
{
    public function testBaseRecordHasNoAcceptanceOrSemanticResults(): void
    {
        $match = $this->getMockForAbstractClass(AbstractMatch::class, [2]);

        $this->assertFalse($match->accepted);
        $this->assertSame(2, $match->length);
        $this->assertSame([], $match->getResults());
        $this->assertSame('fallback', $match->getResult('missing', 'fallback'));
    }

    public function testNegativeProgressIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->getMockForAbstractClass(AbstractMatch::class, [-1]);
    }
}
