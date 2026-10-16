<?php

declare(strict_types=1);

namespace Tests\Src\Builder;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Parser\Structure\ValueSequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;

uses(TestCase::class);

test('using the trait again explicitly opts a custom subclass into facade reuse', function (): void {
    $parser = new class ('a') extends Text {
        use ExactClassSnapshotReuseTrait;
    };
    $facade = C::of($parser);
    $this->assertSame($facade->freeze(), $facade->freeze());
    $this->assertSame('a', $facade->parse('a')->value());
});

test('inheriting a reused trait still excludes further subclasses', function (): void {
    $parser = new class (0, new Text('a')) extends ValueSequence {
        // ValueSequence uses the trait itself, but this subclass has not opted in.
    };
    $facade = C::of($parser);
    $this->assertNotSame($facade->freeze(), $facade->freeze());
    $this->assertSame('a', $facade->parse('a')->value());
});
