<?php

declare(strict_types=1);

namespace Tests\Unit\src\Syntax;

use InvalidArgumentException;
use Countable;
use Tests\Fixtures\Syntax\CapabilityProvider;
use Tests\Fixtures\Syntax\OtherCapabilityProvider;
use Tests\Fixtures\Syntax\PrimaryCapability;
use Tests\Fixtures\Syntax\SecondaryCapability;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Syntax\ExtensionLoader;
use Vanderlee\Comprehend\Syntax\SyntaxExtension;

uses(TestCase::class);

beforeEach(function (): void {
    CapabilityProvider::$constructions = 0;
    CapabilityProvider::$broken = false;
    CapabilityProvider::$recursiveLoader = null;
});

test('capabilities share their instance with factories', function (bool $factoryFirst): void {
    $loader = new ExtensionLoader(['fixture/shared' => CapabilityProvider::manifest([
        'class' => CapabilityProvider::class,
        'providers' => [CapabilityProvider::class => [PrimaryCapability::class, SecondaryCapability::class]],
        'rules' => ['identity'],
        'fluents' => [],
    ])]);
    $this->assertSame(['identity'], $loader->names());
    $this->assertSame(0, CapabilityProvider::$constructions);
    if ($factoryFirst) {
        $loader->rule('identity');
    }
    $first = $loader->requireOne(PrimaryCapability::class);
    $this->assertSame($first, $loader->requireOne(SecondaryCapability::class));
    $this->assertSame($first->identity(), $loader->requireOne(SecondaryCapability::class)->secondaryIdentity());
    $this->assertSame($first, $loader->requireOne(SyntaxExtension::class));
    $this->assertSame([$first], $loader->all(PrimaryCapability::class));
    $this->assertSame([$first], $loader->all(SyntaxExtension::class));
    $this->assertSame((string) $first->identity(), $loader->rule('identity')()->parse('1')->value());
    $this->assertSame(1, CapabilityProvider::$constructions);
})->with([false, true]);

test('only advertised contracts resolve and plural lookup keeps discovery order', function (): void {
    $entry = ['providers' => [CapabilityProvider::class => [SecondaryCapability::class]]];
    $loader = new ExtensionLoader([
        'fixture/z' => CapabilityProvider::manifest($entry, 'z.extension.json'),
        'fixture/a' => CapabilityProvider::manifest($entry, 'a.extension.json'),
    ]);
    $this->assertSame([], $loader->all(PrimaryCapability::class));
    $this->assertSame([], $loader->all(SyntaxExtension::class));
    try {
        $loader->requireOne(SecondaryCapability::class);
        $this->fail('Expected ambiguity');
    } catch (RuntimeException $error) {
        $details = [SecondaryCapability::class, 'fixture/z:', 'fixture/a:', 'z.extension.json', 'a.extension.json'];
        foreach ($details as $text) {
            $this->assertStringContainsString($text, $error->getMessage());
        }
    }
    $this->assertSame(0, CapabilityProvider::$constructions);
    $providers = $loader->all(SecondaryCapability::class);
    $this->assertSame([1, 2], array_map(static fn($provider): int => $provider->identity(), $providers));
    $this->assertSame($providers, $loader->all(SecondaryCapability::class));
    $this->assertSame(2, CapabilityProvider::$constructions);
});

test('missing and invalid lookup contracts fail without loading providers', function (string $contract): void {
    $loader = new ExtensionLoader([]);
    $this->expectException(InvalidArgumentException::class);
    $loader->all($contract);
})->with([stdClass::class, 'MissingCapabilityContract', '']);

test('missing capability has an empty plural result and a descriptive single error', function (): void {
    $loader = new ExtensionLoader([]);
    $this->assertSame([], $loader->all(Countable::class));
    $this->expectExceptionMessage('No extension provides ' . Countable::class);
    $loader->requireOne(Countable::class);
});

test('capability names normalize and identical declarations deduplicate', function (): void {
    $entry = ['providers' => [CapabilityProvider::class => [SecondaryCapability::class, PrimaryCapability::class]]];
    $duplicate = $entry;
    $duplicate['providers'][CapabilityProvider::class] = ['\\' . strtoupper(PrimaryCapability::class),
        strtolower(SecondaryCapability::class),
        PrimaryCapability::class];
    $manifests = static function () use ($entry, $duplicate): iterable {
        yield 'fixture/one' => CapabilityProvider::manifest($entry);
        yield 'fixture/one' => CapabilityProvider::manifest($duplicate);
    };
    $loader = new ExtensionLoader($manifests());
    $this->assertCount(1, $loader->all(PrimaryCapability::class));
    $this->assertSame(
        $loader->requireOne(PrimaryCapability::class),
        $loader->requireOne('\\' . strtoupper(PrimaryCapability::class)),
    );
});

test('all declared interfaces validate lazily before construction', function (array $provides, string $class): void {
    $loader = new ExtensionLoader(['fixture/invalid' => CapabilityProvider::manifest([
        'providers' => [$class => $provides],
    ])]);
    $this->assertSame([], $loader->names());
    $this->assertSame([], $loader->all(Countable::class));
    try {
        $loader->requireOne(PrimaryCapability::class);
        $this->fail('Expected capability validation to fail');
    } catch (RuntimeException $error) {
        foreach (['fixture/invalid:', 'fixture.extension.json', strtolower(PrimaryCapability::class)] as $text) {
            $this->assertStringContainsString($text, $error->getMessage());
        }
    }
    $this->assertSame(0, CapabilityProvider::$constructions);
})->with([
    [[PrimaryCapability::class, 'MissingDeclaredCapability'], CapabilityProvider::class],
    [[PrimaryCapability::class, stdClass::class], CapabilityProvider::class],
    [[PrimaryCapability::class], stdClass::class],
    [[PrimaryCapability::class], 'MissingCapabilityProvider'],
]);

test('failed syntax installation publishes neither instances nor factories and retries', function (): void {
    $loader = new ExtensionLoader(['fixture/retry' => CapabilityProvider::manifest([
        'class' => CapabilityProvider::class,
        'providers' => [CapabilityProvider::class => [PrimaryCapability::class]],
        'rules' => ['identity'],
        'fluents' => [],
    ])]);
    CapabilityProvider::$broken = true;
    try {
        $loader->requireOne(PrimaryCapability::class);
        $this->fail('Expected installation failure');
    } catch (RuntimeException $error) {
        $this->assertStringContainsString('fluent installation failed', $error->getMessage());
    }
    CapabilityProvider::$broken = false;
    $this->assertSame('2', $loader->rule('identity')()->parse('2')->value());
    $this->assertSame(2, $loader->requireOne(PrimaryCapability::class)->identity());
    $this->assertSame(2, CapabilityProvider::$constructions);
});

test('recursive capability loading clears the guard for retry', function (): void {
    $loader = new ExtensionLoader(['fixture/recursive' => CapabilityProvider::manifest([
        'providers' => [CapabilityProvider::class => [PrimaryCapability::class]],
    ])]);
    CapabilityProvider::$recursiveLoader = $loader;
    try {
        $loader->requireOne(PrimaryCapability::class);
        $this->fail('Expected recursive loading to fail');
    } catch (RuntimeException $error) {
        $this->assertStringContainsString('Recursive extension loading', $error->getMessage());
    } finally {
        CapabilityProvider::$recursiveLoader = null;
    }
    $this->assertSame(2, $loader->requireOne(PrimaryCapability::class)->identity());
});

test('malformed capabilities and removed flags fail with source context', function (array $fields): void {
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('fixture.extension.json');
    new ExtensionLoader(['fixture/bad' => CapabilityProvider::manifest($fields)]);
})->with([
    [['providers' => null]], [['providers' => 'invalid']], [['providers' => [PrimaryCapability::class]]],
    [['providers' => [CapabilityProvider::class => null]]],
    [['providers' => [CapabilityProvider::class => 'invalid']]],
    [['providers' => [CapabilityProvider::class => ['key' => PrimaryCapability::class]]]],
    [['providers' => [CapabilityProvider::class => [42]]]],
    [['providers' => [CapabilityProvider::class => ['']]]],
    [['providers' => [CapabilityProvider::class => ['bad-name']]]],
    [['providers' => [CapabilityProvider::class => []]]],
    [['providers' => []]],
    [['providers' => [CapabilityProvider::class => [PrimaryCapability::class]], 'rules' => []]],
    [['providers' => [CapabilityProvider::class => [PrimaryCapability::class]], 'fluents' => []]],
    [['providers' => [CapabilityProvider::class => [SyntaxExtension::class]]]],
    [['provides' => [PrimaryCapability::class]]],
    [['optimizer' => true]], [['optimizer' => false]], [['optimizer' => null]],
]);

test('one extension can declare several classes providing the same contract', function (): void {
    $loader = new ExtensionLoader(['fixture/multiple' => CapabilityProvider::manifest([
        'providers' => [
            OtherCapabilityProvider::class => [PrimaryCapability::class],
            CapabilityProvider::class => [PrimaryCapability::class, SecondaryCapability::class],
        ],
    ])]);
    $providers = $loader->all(PrimaryCapability::class);
    $this->assertInstanceOf(OtherCapabilityProvider::class, $providers[0]);
    $this->assertSame(CapabilityProvider::class, $providers[1]::class);
    $this->assertSame($providers[1], $loader->requireOne(SecondaryCapability::class));
    $this->assertSame([-1, 1], array_map(static fn($provider): int => $provider->identity(), $providers));
    $this->expectExceptionMessage('Ambiguous extension capability ' . PrimaryCapability::class);
    $loader->requireOne(PrimaryCapability::class);
});
