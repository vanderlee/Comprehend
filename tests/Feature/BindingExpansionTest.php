<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Binding;
use Vanderlee\Comprehend\Parser\Structure\SeparatedList;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;

uses(TestCase::class);

test('binding authoring is opt in and does not reserve extraction names in core', function (string $name): void {
    $this->assertNotContains(strtolower($name), C::syntaxNames());
    $syntax = C::syntax()->defineRule($name, static fn(): C => C::text('custom'));
    $this->assertSame('custom', $syntax->call($name)->parse('custom')->value());
    $this->expectException(BadMethodCallException::class);
    C::syntax()->call($name);
})->with([
    'collect', 'valueChoice', 'manyValues', 'someValues', 'repeatValues', 'optionalValue', 'separatedBy',
    'map', 'mapText', 'between', 'thenIgnore', 'ignoreThen',
]);

test('unscoped expressions do not acquire binding fluent operations', function (string $method): void {
    $this->expectException(BadMethodCallException::class);
    C::text('a')->$method();
})->with(['map', 'mapText', 'between', 'thenIgnore', 'ignoreThen']);

test('binding attaches explicitly and leaves original vocabularies and expressions unchanged', function (): void {
    $base = C::syntax();
    $plain = $base->text('1');
    $syntax = $base->extend(new Binding());
    $bound = $syntax->of($plain)->mapText('intval');
    $this->assertSame(1, $bound->parse('1')->value());
    $this->assertSame('1', $plain->parse('1')->value());
    $this->assertNotContains('collect', $base->names());
    $this->assertSame([1, 1], $syntax->collect($bound, $bound)->parse('11')->value());
    $this->assertSame([1], $syntax->manyValues($bound)->parse('1')->value());
});

test('core separated lists recognize syntax while binding lists project item values', function (): void {
    $plain = new SeparatedList(new Text('a'), new Text(','));
    $this->assertSame('a,a', $plain->parseAll(new ByteSource('a,a'))->value());
    $bound = Binding::syntax()->text('a')->map(static fn(string $s): int => 1)->separatedBy(',');
    $result = $bound->parse('a,a');
    $this->assertSame([1, 1], $result->value());
    $this->assertSame('a,a', $result->rawText());
});
