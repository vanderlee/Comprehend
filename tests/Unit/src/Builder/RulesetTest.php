<?php

declare(strict_types=1);

namespace Tests\Src\Builder;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Parser\Terminal\Text;

uses(TestCase::class);

test('redefinition affects future snapshots', function (): void {
    $rules = (new Ruleset(['item' => new Text('a')]))->root('item');
    $frozen = $rules->freeze();
    $rules->define('item', new Text('b'));

    $this->assertTrue($frozen->parse('a')->success);
    $this->assertFalse($frozen->parse('b')->success);
    $this->assertTrue($rules->freeze()->parse('b')->success);
});

test('empty rule names are rejected', function (): void {
    $this->expectException(InvalidArgumentException::class);

    (new Ruleset())->define('', new Text('a'));
});
