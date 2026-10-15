<?php

declare(strict_types=1);

namespace Tests\Src\Parser\Structure;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Structure\IterableParser;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

final class IterableParserTest extends TestCase
{
    public function testArrayOperationsAndIterationUseTheSameChildren(): void
    {
        $parser = $this->container();
        $child = new Text('a');
        $parser[] = $child;
        $parser['named'] = $child;
        unset($parser[0]);

        $this->assertFalse(isset($parser[0]));
        $this->assertNull($parser[0]);
        $this->assertSame($child, $parser['named']);
        $this->assertSame(['named' => $child], iterator_to_array($parser));
    }

    public function testChildrenMustBeParsers(): void
    {
        $parser = $this->container();
        $this->expectException(InvalidArgumentException::class);

        $parser[] = 'a';
    }

    private function container(): IterableParser
    {
        return new class extends IterableParser {
            protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
            {
                return $this->success($source, $byteOffset);
            }

            public function __toString(): string
            {
                return 'container';
            }
        };
    }
}
