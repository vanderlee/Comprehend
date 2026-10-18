<?php

declare(strict_types=1);

namespace Tests\Extensions;

use PHPUnit\Framework\TestCase;
use Tests\Fixtures\LibraryFactoryCases;
use Vanderlee\Comprehend\Builder\FreezeFlags;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Network;
use Vanderlee\Comprehend\Syntax\ExtensionLoader;

uses(TestCase::class);

test('subject aliases preserve their specification rule trees and textual values', function (
    string $name,
    string $class,
    string $rule,
    string $input,
): void {
    $this->assertNotContains(strtolower($name), C::syntaxNames());
    $this->assertContains(strtolower($name), C::syntax()->names());
    $direct = (new $class())->rule($rule)->freeze()->parse($input);
    $discovered = C::$name()->freeze()->parse($input);
    $ordinary = C::syntax()->$name()->freeze(FreezeFlags::NONE)->parse($input);
    $this->assertTrue($discovered->success, $name . ': ' . $input);
    $this->assertSame($input, $discovered->value());
    $this->assertSame($input, $discovered->rawText());
    $this->assertEquals($direct->node, $discovered->node);
    $this->assertEquals($ordinary->node, $discovered->node);
})->with(LibraryFactoryCases::aliases());

test('versioned factories select explicit rules without replacing historical grammars', function (
    string $name,
    string $class,
    string $root,
    string $input,
): void {
    $default = C::$name()->parse($input);
    $explicit = C::$name(rule: $root)->parse($input);
    $direct = (new $class())->rule($root)->parse($input);
    $this->assertTrue($default->success, $name . ': ' . $input);
    $this->assertEquals($direct->node, $default->node);
    $this->assertEquals($explicit->node, $default->node);
    $this->assertSame($input, $default->value());
})->with(LibraryFactoryCases::versions());

test('subject manifests match registrations and cover every implemented RFC exactly once', function (): void {
    $versions = [];
    foreach (glob(dirname(__DIR__, 3) . '/extensions/*.extension.json') as $path) {
        $entry = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $class = $entry['class'] ?? '';
        if (!is_subclass_of($class, \Vanderlee\Comprehend\Extensions\Library\SpecificationExtension::class)) {
            continue;
        }
        $loader = new ExtensionLoader(['fixture/subject' => ['extra' => ['comprehend' => ['extensions' => [$entry]]]]]);
        $this->assertSame([], iterator_to_array((new $class())->fluents()));
        foreach ($entry['rules'] as $name) {
            $this->assertNotNull($loader->rule($name));
            if (preg_match('/^rfc\d+$/', $name)) {
                $versions[] = substr($name, 3);
            }
        }
    }
    $implemented = array_map(
        static fn(string $file): string => substr(basename($file, '.php'), 3),
        glob(dirname(__DIR__, 3) . '/extensions/Library/Rfc*.php'),
    );
    sort($versions);
    sort($implemented);
    $this->assertSame($implemented, $versions);
});

test('factory chaining overrides and authoring metadata remain local', function (): void {
    $this->assertSame('@192.0.2.1', C::text('@')->ipv4()->parse('@192.0.2.1')->value());
    $syntax = C::syntax()->defineRule('uuid', static fn(): C => C::text('custom'));
    $this->assertSame('custom', $syntax->uuid()->parse('custom')->value());
    $this->assertTrue(C::uuid()->parse('550e8400-e29b-41d4-a716-446655440000')->success);
    $this->assertSame('example.org', $syntax->extend(new Network())->uriHost()->parse('example.org')->value());
    $this->assertSame('443', C::text('@')->ignoreThen(C::rfc3986(rule: 'port'))->parse('@443')->value());
    $this->assertSame('42', C::rfc5234(rule: 'DIGIT')->some()->parse('42')->value());

    $effects = 0;
    $mapped = 0;
    $decorated = C::ipv4()->named('ip')->mapValue(static function () use (&$mapped): string {
        ++$mapped;
        return 'mapped';
    })->onCommit(static function () use (&$effects): void {
        ++$effects;
    });
    $result = $decorated->parse('192.0.2.1');
    $this->assertSame(0, $mapped);
    $this->assertSame(0, $effects);
    $this->assertSame('mapped', $result->value());
    $this->assertSame('mapped', $result->value());
    $this->assertSame(1, $mapped);
    $result->commit();
    $result->commit();
    $this->assertSame(1, $effects);
    $this->assertSame('192.0.2.1', C::ipv4()->parse('192.0.2.1')->value());
});

test('arbitrary versioned rule selection retains unknown-rule errors', function (): void {
    $this->assertSame('example.org', C::rfc3986(rule: 'host')->parse('example.org')->value());
    $this->expectException(\LogicException::class);
    C::rfc3986(rule: 'unknown_rule')->freeze();
});
