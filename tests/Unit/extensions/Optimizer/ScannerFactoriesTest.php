<?php

declare(strict_types=1);

namespace Tests\Extensions\Optimizer;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\GraphOptimization;
use Vanderlee\Comprehend\Builder\FreezeFlags;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Optimizer\GraphOptimizer;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadRest;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadUntil;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadWhile;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadWhileRange;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadWhileSet;
use Vanderlee\Comprehend\Syntax\ExtensionLoader;

uses(TestCase::class);

test('scanner factories discover and append with optimization disabled', function (
    string $name,
    array $arguments,
    string $class,
    ?string $value,
): void {
    $this->assertNotContains(strtolower($name), C::syntaxNames());
    $this->assertContains(strtolower($name), C::syntax()->names());
    $parser = C::$name(...$arguments);
    $this->assertInstanceOf($class, $parser->parser());
    $ordinary = $parser->freeze(FreezeFlags::NONE)->parse('abc');
    $optimized = $parser->freeze()->parse('abc');
    $this->assertTrue($ordinary->success);
    $this->assertSame($value, $ordinary->value());
    $this->assertSame($value, $optimized->value());
    $this->assertEquals($ordinary->node, $optimized->node);
    $this->assertSame($class, $ordinary->node->kind);
    $this->assertSame('abc', $ordinary->node->rawText());
    $this->assertTrue(C::text('@')->$name(...$arguments)->freeze(FreezeFlags::NONE)->parse('@abc')->success);
    $this->assertSame($value, C::syntax()->$name(...$arguments)->parse('abc')->value());
})->with([
    'readWhile' => ['readWhile', [static fn(string $unit): bool => ctype_alpha($unit)], ReadWhile::class, 'abc'],
    'readUntil' => ['readUntil', ['delimiter' => ';', 'min' => 1, 'max' => 3], ReadUntil::class, 'abc'],
    'readWhileSet' => ['readWhileSet', ['characters' => 'abc', 'min' => 1], ReadWhileSet::class, 'abc'],
    'readWhileRange' => ['readWhileRange', ['first' => 'a', 'last' => 'z'], ReadWhileRange::class, 'abc'],
    'readRest' => ['readRest', [], ReadRest::class, 'abc'],
    'skipWhile' => ['skipWhile', [static fn(string $unit): bool => ctype_alpha($unit)], ReadWhile::class, null],
    'skipUntil' => ['skipUntil', ['delimiter' => ';'], ReadUntil::class, null],
    'skipRest' => ['skipRest', [], ReadRest::class, null],
]);

test('scanner declarations match factories and allow local overrides', function (): void {
    $manifest = json_decode(
        file_get_contents(dirname(__DIR__, 4) . '/extensions/Optimizer/Optimizer.extension.json'),
        true,
    );
    $loader = new ExtensionLoader([
        'fixture/optimizer' => ['extra' => ['comprehend' => ['extensions' => [$manifest]]]],
    ]);
    $names = array_map('strtolower', $manifest['rules']);
    sort($names);
    $this->assertSame($names, $loader->names());
    foreach ($names as $name) {
        $this->assertNotNull($loader->rule($name));
    }
    $this->assertInstanceOf(GraphOptimizer::class, $loader->requireOne(GraphOptimization::class));
    $this->assertSame([], iterator_to_array((new GraphOptimizer())->fluents()));

    $base = C::syntax();
    $override = $base->defineRule('readUntil', static fn(): C => C::text('custom'));
    $this->assertSame('custom', $override->readUntil()->parse('custom')->value());
    $this->assertSame('abc', $base->readUntil(';')->parse('abc')->value());
    $this->assertSame('abc', C::readUntil(';')->parse('abc')->value());
    $this->assertSame('abc', $override->extend(new GraphOptimizer())->readUntil(';')->parse('abc')->value());
});
