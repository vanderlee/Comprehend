<?php

declare(strict_types=1);

namespace Tests\Src\Builder;

use LogicException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\AbstractRuleset;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Terminal\Text;

uses(TestCase::class);

test('subclass group and root are used for recognition', function (): void {
    $rules = $this->getMockForAbstractClass(AbstractRuleset::class, [['item' => new Text('a')], 'example']);
    $rules->root('item');
    $result = $rules->freeze()->parse('a');

    $this->assertTrue($result->success);
    $this->assertSame('item', $result->ast()->firstChild()->name);
    $this->assertSame('example', $result->ast()->firstChild()->group);
    $this->assertSame($rules->graphIdentity(), $rules->graphIdentity());
});

test('undefining a rule invalidates its cached implementation', function (): void {
    $rules = $this->getMockForAbstractClass(AbstractRuleset::class, [['item' => new Text('a')]]);
    $rules->resolveRule('item');
    $rules->undefine('item');

    $this->assertFalse($rules->defined('item'));
    $this->expectException(LogicException::class);
    $rules->resolveRule('item');
});

test('fresh resolution does not replace the cached implementation', function (): void {
    $calls = 0;
    $rules = $this->getMockForAbstractClass(AbstractRuleset::class);
    $rules->define('item', static function () use (&$calls): Parser {
        ++$calls;

        return new Text((string) $calls);
    });

    $cached = $rules->resolveRule('item');
    $fresh = $rules->resolveRule('item', fresh: true);

    $this->assertNotSame($cached, $fresh);
    $this->assertSame($cached, $rules->resolveRule('item'));
    $this->assertSame(2, $calls);
});
