<?php

declare(strict_types=1);

namespace Tests\Src\Builder;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\FrozenGrammar;
use Vanderlee\Comprehend\Parser\Terminal\Text;

final class FrozenGrammarTest extends TestCase
{
    public function testSnapshotIsIndependentOfLaterParserMutations(): void
    {
        $parser = new Text('a');
        $frozen = FrozenGrammar::from($parser);
        $parser->guard(static fn(): bool => false);

        $this->assertTrue($frozen->parse('a')->success);
        $this->assertSame([], $frozen->analysisWarnings);
        $this->assertSame('a', $frozen->matchPrefix('xab', 1)->rawText());
        $this->assertFalse($frozen->parseAll('xab', 1)->success);
    }
}
