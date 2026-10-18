<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Extraction\Extraction;
use Vanderlee\Comprehend\Parser\Structure\SeparatedList;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;

uses(TestCase::class);

test('discovered extraction names remain available for explicit overrides', function (string $name): void {
    $this->assertNotContains(strtolower($name), C::syntaxNames());
    $syntax = C::syntax()->defineRule($name, static fn(): C => C::text('custom'));
    $this->assertSame('custom', $syntax->call($name)->parse('custom')->value());
    $this->assertContains(strtolower($name), C::syntax()->names());
})->with([
    'collect', 'record', 'valueChoice', 'manyValues', 'someValues', 'repeatValues', 'optionalValue',
    'map', 'mapText',
]);

test('unscoped expressions load extraction fluent operations', function (string $method): void {
    $this->assertSame('A', C::text('a')->$method('strtoupper')->parse('a')->value());
})->with(['map', 'mapText']);

test('extraction attaches explicitly and leaves original vocabularies and expressions unchanged', function (): void {
    $base = C::syntax();
    $plain = $base->text('1');
    $syntax = $base->extend(new Extraction());
    $bound = $syntax->of($plain)->mapText('intval');
    $this->assertSame(1, $bound->parse('1')->value());
    $this->assertSame('1', $plain->parse('1')->value());
    $this->assertContains('collect', $base->names());
    $this->assertSame([1, 1], $syntax->collect($bound, $bound)->parse('11')->value());
    $this->assertSame([1], $syntax->manyValues($bound)->parse('1')->value());
});

test('core separated lists project item values with or without extraction', function (): void {
    $plain = new SeparatedList(new Text('a'), new Text(','));
    $this->assertSame(['a', 'a'], $plain->parseAll(new ByteSource('a,a'))->value());
    $bound = Extraction::syntax()->text('a')->map(static fn(string $s): int => 1)->separatedBy(',');
    $result = $bound->parse('a,a');
    $this->assertSame([1, 1], $result->value());
    $this->assertSame('a,a', $result->rawText());
});

test('core structural operations select values without installing extraction', function (): void {
    $number = C::integer()->mapValue(static fn($result): int => (int) $result->rawText());
    $list = $number->separatedBy(',')->between('[', ']')->thenIgnore(';');
    $result = C::text('values=')->ignoreThen($list)->parse('values=[1,2];');
    $this->assertSame([1, 2], $result->value());
    $this->assertSame('values=[1,2];', $result->rawText());
    $this->assertFalse($list->parse('[1,2;')->success);
    $this->assertSame(['a', 'a'], C::separatedBy('a', ',')->parse('a,a')->value());
    $this->assertSame(['a'], C::syntax()->separatedBy('a', ',')->parse('a')->value());
    foreach (['separatedBy', 'thenIgnore', 'ignoreThen', 'between'] as $name) {
        $this->assertContains(strtolower($name), C::syntaxNames());
    }
});
