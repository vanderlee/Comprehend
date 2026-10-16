<?php

declare(strict_types=1);

namespace Tests\Extensions\Expression;

use LogicException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Extensions\Expression\Parser\ValueRepeat;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Text;

uses(TestCase::class);

test('value repeats retain independent lazy child values and normal progress checks', function (): void {
    $calls = 0;
    $item = (new Text('a'))->mapValue(static function () use (&$calls): int {
        return ++$calls;
    });
    $parser = new ValueRepeat($item);
    $result = $parser->freeze()->parse('aa');
    $this->assertSame(0, $calls);
    $this->assertSame([1, 2], $result->value());
    $this->assertSame([1, 2], $result->value());
    $this->assertSame([], $parser->freeze()->parse('')->value());
    $this->expectException(LogicException::class);
    (new ValueRepeat(new Nothing()))->freeze();
});
