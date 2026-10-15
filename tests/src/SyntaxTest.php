<?php

declare(strict_types=1);

namespace Tests\Src;

use BadMethodCallException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Syntax;

uses(TestCase::class);

test('definitions create independent vocabularies', function (): void {
    $original = Syntax::create();
    $extended = $original->define('greeting', static fn(): Text => new Text('hello'));

    $this->assertNotContains('greeting', $original->names());
    $this->assertContains('greeting', $extended->names());
    $this->assertTrue($extended->call('greeting')->parse('hello')->success);

    $this->expectException(BadMethodCallException::class);
    $original->call('greeting');
});
