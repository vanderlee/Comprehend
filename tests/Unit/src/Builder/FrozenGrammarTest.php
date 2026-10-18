<?php

declare(strict_types=1);

namespace Tests\Src\Builder;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\FrozenGrammar;
use Vanderlee\Comprehend\Parser\Terminal\Text;

uses(TestCase::class);

test('snapshot is independent of later parser mutations', function (): void {
    $parser = new Text('a');
    $frozen = FrozenGrammar::from($parser);
    $parser->guard(static fn(): bool => false);

    $this->assertTrue($frozen->parse('a')->success);
    $this->assertSame([], $frozen->analysisWarnings);
    $this->assertSame('a', $frozen->matchPrefix('xab', 1)->rawText());
    $this->assertFalse($frozen->parseAll('xab', 1)->success);
});

test('only a built-in source can reuse a snapshot of an entirely built-in graph', function () {
    $parser = new Text('a');
    $frozen = FrozenGrammar::from($parser);
    $this->assertTrue($frozen->canReuseFor($parser));
    $this->assertFalse($frozen->canReuseFor(new class ('a') extends Text {
        public function __toString(): string
        {
            return 'custom text';
        }
    }));
});
