<?php

declare(strict_types=1);

namespace Tests\Unit\src\Syntax;

use Composer\InstalledVersions;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Fixtures\Syntax\BrokenDiscovery;
use Tests\Fixtures\Syntax\ConfiguredDiscovery;
use Tests\Fixtures\Syntax\DiscoveredWords;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Syntax;
use Vanderlee\Comprehend\Syntax\ExtensionLoader;
use Vanderlee\Comprehend\Syntax\SyntaxExtension;

uses(TestCase::class);

test('discovery lists names without constructing providers and caches only registrations', function (): void {
    DiscoveredWords::$constructions = DiscoveredWords::$calls = 0;
    $loader = new ExtensionLoader(['acme/words' => DiscoveredWords::manifest()]);
    $this->assertSame(['discoveredword'], $loader->names());
    $this->assertNull($loader->rule('missing'));
    $this->assertSame(0, DiscoveredWords::$constructions);
    $rule = $loader->rule('DISCOVEREDWORD');
    $this->assertSame(1, DiscoveredWords::$constructions);
    $this->assertSame(0, DiscoveredWords::$calls);
    $first = $rule(text: 'first');
    $second = $loader->rule('discoveredWord')(text: 'second');
    $this->assertSame('first', $first->parse('first')->value());
    $this->assertSame('second!', $loader->fluent('discoveredWord')($second)->parse('second!')->value());
    $this->assertSame(1, DiscoveredWords::$constructions);
    $this->assertSame(2, DiscoveredWords::$calls);
});

test('ambiguous providers fail only when the conflicting kind and name are used', function (): void {
    $loader = new ExtensionLoader([
        'acme/one' => DiscoveredWords::manifest(),
        'acme/two' => DiscoveredWords::manifest(),
    ]);
    $this->assertContains('discoveredword', $loader->names());
    $this->assertNull($loader->rule('unrelated'));
    $this->expectExceptionMessage('acme/one:');
    try {
        $loader->fluent('discoveredWord');
    } catch (RuntimeException $error) {
        $this->assertStringContainsString('acme/two:', $error->getMessage());
        throw $error;
    }
});

test('duplicate identical provider declarations do not create ambiguity', function (): void {
    $manifest = DiscoveredWords::manifest();
    $manifest['extra']['comprehend']['extensions'][] = $manifest['extra']['comprehend']['extensions'][0];
    $loader = new ExtensionLoader(['acme/words' => $manifest]);
    $this->assertSame('word', $loader->rule('discoveredWord')()->parse('word')->value());
});

test('failed provider installation never publishes partial rules and can be retried', function (): void {
    BrokenDiscovery::$broken = true;
    $loader = new ExtensionLoader([
        'acme/broken' => DiscoveredWords::manifest(BrokenDiscovery::class, ['discoveredWord'], []),
    ]);
    try {
        $loader->rule('discoveredWord');
        $this->fail('Expected installation to fail');
    } catch (RuntimeException $error) {
        $this->assertStringContainsString('acme/broken:', $error->getMessage());
        $this->assertStringContainsString('fluent registration failed', $error->getMessage());
    } finally {
        BrokenDiscovery::$broken = false;
    }
    $this->assertSame('ok', $loader->rule('discoveredWord')()->parse('ok')->value());
});

test('invalid providers fail lazily with package context', function (
    string $class,
    array $rules,
    array $fluents,
    string $message,
): void {
    $loader = new ExtensionLoader(['acme/invalid' => DiscoveredWords::manifest($class, $rules, $fluents)]);
    $this->assertContains(strtolower($rules[0]), $loader->names());
    try {
        $loader->rule($rules[0]);
        $this->fail('Expected provider validation to fail');
    } catch (RuntimeException $error) {
        $this->assertStringContainsString('acme/invalid:', $error->getMessage());
        $this->assertStringContainsString($message, $error->getMessage());
    }
})->with([
    ['MissingExtensionProvider', ['word'], [], 'does not exist'],
    [\stdClass::class, ['word'], [], 'implement ' . SyntaxExtension::class],
    [ConfiguredDiscovery::class, ['word'], [], 'Too few arguments'],
    [DiscoveredWords::class, ['differentName'], ['discoveredWord'], 'do not match'],
    [DiscoveredWords::class, ['discoveredWord'], [], 'do not match'],
]);

test('malformed metadata fails discovery with package context', function (array $manifest): void {
    $this->expectExceptionMessage('acme/invalid');
    new ExtensionLoader(['acme/invalid' => $manifest]);
})->with([
    [['extra' => 'invalid']],
    [['extra' => ['comprehend' => 'invalid']]],
    [['extra' => ['comprehend' => ['extensions' => 'invalid']]]],
    [['extra' => ['comprehend' => ['extensions' => null]]]],
    [DiscoveredWords::manifest('', ['word'], [])],
    [DiscoveredWords::manifest(DiscoveredWords::class, ['bad-name'], [])],
]);

test('bundled factories compose without registration and retain custom vocabularies', function (): void {
    $syntax = C::syntax()->defineRule('suffix', static fn(): C => C::text('!'));
    $plain = $syntax->asciiIdentifier();
    $mapped = $plain->map('strtoupper')->suffix();
    $this->assertSame('name', $plain->parse('name')->value());
    $this->assertSame('name!', $mapped->parse('name!')->value());
    $this->assertSame(['NAME'], C::collect(C::asciiIdentifier()->map('strtoupper'))->parse('name')->value());
    $this->assertSame('7', C::operatorExpression('7')->parse('7')->value());
    $this->assertTrue(C::text('ok')->recoverUntil(';')->matchPrefix('bad;')->hasRecovery());
    $ruleOverride = $syntax->defineRule('collect', static fn(): C => C::text('custom'));
    $fluentOverride = $syntax->defineFluent('map', static fn(C $item): C => $item->then('ustom'));
    $this->assertSame('custom', $ruleOverride->collect()->parse('custom')->value());
    $this->assertSame('custom', $fluentOverride->text('c')->map()->parse('custom')->value());
});

test('discovered providers cannot register reserved names', function (): void {
    $provider = new class implements SyntaxExtension {
        public function rules(): iterable
        {
            yield 'text' => static fn(): C => C::text('override');
        }

        public function fluents(): iterable
        {
            return [];
        }
    };
    $loader = new ExtensionLoader(['acme/reserved' => DiscoveredWords::manifest($provider::class, ['text'], [])]);
    $this->expectExceptionMessage('Syntax name is reserved: text');
    $loader->rule('text');
});

test('recursive provider loading fails without publishing factories', function (): void {
    $provider = new class implements SyntaxExtension {
        public static ?ExtensionLoader $loader = null;

        public function rules(): iterable
        {
            self::$loader->rule('recursiveWord');

            return [];
        }

        public function fluents(): iterable
        {
            return [];
        }
    };
    $loader = new ExtensionLoader([
        'acme/recursive' => DiscoveredWords::manifest($provider::class, ['recursiveWord'], []),
    ]);
    $provider::$loader = $loader;
    $this->expectExceptionMessage('Recursive extension loading: acme/recursive:');
    $loader->rule('recursiveWord');
});

test('Composer discovery reads dependency and root manifests through normal dispatch', function (): void {
    // Isolate the process-wide discovery cache while exercising real Composer installation data.
    $directory = sys_get_temp_dir() . '/comprehend-discovery-' . bin2hex(random_bytes(6));
    mkdir($directory);
    mkdir($directory . '/dependency');
    $autoload = dirname(__DIR__, 4) . '/vendor/autoload.php';
    $root = DiscoveredWords::manifest();
    $root['name'] = 'fixture/root';
    $dependency = DiscoveredWords::manifest(DiscoveredWords::class, ['discoveredWord'], ['discoveredWord']);
    $dependency['name'] = 'fixture/dependency';
    file_put_contents($directory . '/composer.json', json_encode($root, JSON_THROW_ON_ERROR));
    file_put_contents($directory . '/dependency/composer.json', json_encode($dependency, JSON_THROW_ON_ERROR));
    $script = '<?php require ' . var_export($autoload, true) . ';'
        . 'final class FixtureWords implements ' . SyntaxExtension::class . ' {'
        . 'public function rules(): iterable { return ["discoveredWord" => fn(string $text = "word") => '
        . C::class . '::text($text)]; }'
        . 'public function fluents(): iterable { return ["discoveredWord" => fn($item) => $item->then("!")]; }}'
        . InstalledVersions::class . '::reload(' . var_export([
            'root' => ['name' => 'fixture/root', 'install_path' => $directory],
            'versions' => ['fixture/dependency' => ['install_path' => $directory . '/dependency']],
        ], true) . ');'
        . 'try { ' . C::class . '::discoveredWord(); exit(2); }'
        . 'catch (RuntimeException $e) { if (!str_contains($e->getMessage(), "fixture/root:")'
        . ' || !str_contains($e->getMessage(), "fixture/dependency:")) { throw $e; } }'
        . 'try { ' . C::class . '::text("word")->discoveredWord(); exit(6); }'
        . 'catch (RuntimeException $e) { if (!str_contains($e->getMessage(), "fluents")) { throw $e; } }'
        . '$syntax = ' . Syntax::class . '::create()->defineRule("discoveredWord", fn() => '
        . C::class . '::text("override"));'
        . 'if (!$syntax->discoveredWord()->parse("override")->success) { exit(3); }'
        . '$syntax = $syntax->defineFluent("discoveredWord", fn($item) => $item->then("!"));'
        . 'if (!$syntax->text("word")->discoveredWord()->parse("word!")->success) { exit(7); }';
    file_put_contents($directory . '/check.php', $script);
    try {
        $output = [];
        $status = 0;
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($directory . '/check.php') . ' 2>&1', $output, $status);
        $this->assertSame(0, $status, implode("\n", $output));
        // Fresh processes separately exercise root-only and dependency-only provider loading.
        $root['extra']['comprehend']['extensions'][0]['class'] = 'FixtureWords';
        file_put_contents($directory . '/composer.json', json_encode($root, JSON_THROW_ON_ERROR));
        file_put_contents($directory . '/dependency/composer.json', '{}');
        $script = substr($script, 0, strpos($script, 'try {'))
            . '$expression = ' . C::class . '::discoveredWord(text: "hello")->discoveredWord();'
            . 'if ($expression->parse("hello!")->value() !== "hello!") { exit(4); }';
        file_put_contents($directory . '/check.php', $script);
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($directory . '/check.php') . ' 2>&1', $output, $status);
        $this->assertSame(0, $status, implode("\n", $output));
        $sourceRoot = dirname(__DIR__, 4);
        $script = '<?php spl_autoload_register(static function ($class) {'
            . '$prefix = "Vanderlee\\\\Comprehend\\\\";'
            . 'if (!str_starts_with($class, $prefix)) { return; }'
            . '$relative = substr($class, strlen($prefix));'
            . '$directory = str_starts_with($relative, "Extensions\\\\") ? "extensions/" : "src/";'
            . 'if ($directory === "extensions/") { $relative = substr($relative, 11); }'
            . '$file = ' . var_export($sourceRoot . '/', true)
            . '.$directory.str_replace("\\\\", "/", $relative).".php";'
            . 'if (is_file($file)) { require $file; }});'
            . '$parser = ' . C::class . '::collect(' . C::class . '::asciiIdentifier()->map("strtoupper"));'
            . 'if (!$parser instanceof ' . C::class . ') { exit(5); }'
            . 'foreach (["integer" => "12", "binary" => "101", "octal" => "17",'
            . '"hexadecimal" => "ff", "octet" => "255", "float" => "1.25"] as $name => $text) {'
            . 'if (' . C::class . '::$name()->parse($text)->value() !== $text) { exit(8); }}'
            . 'if (' . C::class . '::integerValue(base: 16)->parse("ff")->value() !== 255) { exit(9); }'
            . 'if (' . C::class . '::floatValue()->parse("1.5")->value() !== 1.5) { exit(10); }';
        file_put_contents($directory . '/check.php', $script);
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($directory . '/check.php') . ' 2>&1', $output, $status);
        $this->assertSame(0, $status, implode("\n", $output));
        file_put_contents($directory . '/composer.json', '{}');
        $root['name'] = 'fixture/dependency';
        file_put_contents($directory . '/dependency/composer.json', json_encode($root, JSON_THROW_ON_ERROR));
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($directory . '/check.php') . ' 2>&1', $output, $status);
        $this->assertSame(0, $status, implode("\n", $output));
    } finally {
        unlink($directory . '/check.php');
        unlink($directory . '/dependency/composer.json');
        rmdir($directory . '/dependency');
        unlink($directory . '/composer.json');
        rmdir($directory);
    }
});
