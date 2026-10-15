<?php

declare(strict_types=1);

namespace Tests\Src\Builder;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\GraphState;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;

final class GraphStateTest extends TestCase
{
    public function testChildrenExposeOwnedParsersWithoutExecutingCallbacks(): void
    {
        $calls = 0;
        $child = (new Text('a'))->mapValue(static function () use (&$calls): int {
            return ++$calls;
        });
        $parser = new Sequence($child);

        $this->assertSame([$child], GraphState::children($parser));
        $this->assertSame([$child], GraphState::value($parser, 'parsers'));
        $this->assertNull(GraphState::value($parser, 'missing'));
        $this->assertSame(0, $calls);
    }
}
