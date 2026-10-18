<?php

declare(strict_types=1);

namespace Tests\Unit\src\Syntax;

use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Syntax\DiscoveredWords;
use Tests\Fixtures\Syntax\DiscoveryProject;
use Vanderlee\Comprehend\Comprehend as C;

uses(TestCase::class);

test('file providers load from packages without composer manifests', function (string $prefix): void {
    $project = new DiscoveryProject();
    try {
        $entry = DiscoveredWords::manifest()['extra']['comprehend']['extensions'][0];
        $project->write($prefix . 'extensions/Words.extension.json', $entry);
        $project->write($prefix . 'extensions/ignored.json', '{');
        $project->write($prefix . 'extensions/nested/ignored.extension.json', '{');
        $result = $project->run(
            'if (' . DiscoveredWords::class . '::$constructions !== 0) { exit(2); }'
            . '$syntax = ' . C::class . '::syntax();'
            . 'if (!in_array("discoveredword", $syntax->names(), true)) { exit(3); }'
            . 'if (' . DiscoveredWords::class . '::$constructions !== 0) { exit(4); }'
            . '$parser = $syntax->discoveredWord(text: "hello")->discoveredWord();'
            . 'if ($parser->parse("hello!")->value() !== "hello!") { exit(5); }'
            . 'if (' . DiscoveredWords::class . '::$constructions !== 1) { exit(6); }',
        );
        $this->assertSame(0, $result['status'], $result['output']);
    } finally {
        $project->clean();
    }
})->with(['', 'dependency/']);

test('inline and file declarations merge without loading a provider twice', function (): void {
    $project = new DiscoveryProject();
    try {
        $manifest = DiscoveredWords::manifest();
        $entry = $manifest['extra']['comprehend']['extensions'][0];
        $project->write('composer.json', $manifest);
        $project->write('extensions/Words.extension.json', $entry);
        $project->write('extensions/Duplicate.extension.json', $entry);
        $result = $project->run(
            '$first = ' . C::class . '::discoveredWord();'
            . '$second = ' . C::class . '::discoveredWord();'
            . 'if (!$first->parse("word")->success || !$second->parse("word")->success) { exit(2); }'
            . 'if (' . DiscoveredWords::class . '::$constructions !== 1) { exit(3); }',
        );
        $this->assertSame(0, $result['status'], $result['output']);
    } finally {
        $project->clean();
    }
});

test('file errors identify package and manifest path', function (string $contents, string $operation): void {
    $project = new DiscoveryProject();
    try {
        $file = 'dependency/extensions/Bad.extension.json';
        $project->write($file, $contents);
        $result = $project->run(
            'try { ' . $operation . '; exit(2); }'
            . 'catch (RuntimeException $error) { echo $error->getMessage(); }',
        );
        $this->assertSame(0, $result['status'], $result['output']);
        $this->assertStringContainsString('fixture/dependency', $result['output']);
        $this->assertStringContainsString('Bad.extension.json', $result['output']);
        $this->assertStringContainsString('extensions', $result['output']);
    } finally {
        $project->clean();
    }
})->with([
    ['{', C::class . '::syntax()->names()'],
    ['[]', C::class . '::syntax()->names()'],
    ['{}', C::class . '::syntax()->names()'],
    ['{"class":"MissingProvider","rules":["bad-name"],"fluents":[]}', C::class . '::syntax()->names()'],
    ['{"class":"MissingProvider","rules":["word"],"fluents":[]}', C::class . '::word()'],
]);

test('inconsistent inline and file declarations identify both sources', function (): void {
    $project = new DiscoveryProject();
    try {
        $project->write('composer.json', DiscoveredWords::manifest());
        $entry = DiscoveredWords::manifest()['extra']['comprehend']['extensions'][0];
        $entry['rules'] = ['differentWord'];
        $project->write('extensions/Words.extension.json', $entry);
        $result = $project->run(
            'try { ' . C::class . '::syntax()->names(); exit(2); }'
            . 'catch (RuntimeException $error) { echo $error->getMessage(); }',
        );
        $this->assertSame(0, $result['status'], $result['output']);
        $this->assertStringContainsString('fixture/root:', $result['output']);
        $this->assertStringContainsString('composer.json', $result['output']);
        $this->assertStringContainsString('Words.extension.json', $result['output']);
    } finally {
        $project->clean();
    }
});

test('file conflicts fail on use and allow explicit vocabulary overrides', function (): void {
    $project = new DiscoveryProject();
    try {
        $entry = DiscoveredWords::manifest()['extra']['comprehend']['extensions'][0];
        $project->write('extensions/Words.extension.json', $entry);
        $project->write('dependency/extensions/Words.extension.json', $entry);
        $result = $project->run(
            '$syntax = ' . C::class . '::syntax(); $syntax->names();'
            . 'try { ' . C::class . '::discoveredWord(); exit(2); }'
            . 'catch (RuntimeException $error) { echo $error->getMessage(); }'
            . '$syntax = $syntax->defineRule("discoveredWord", fn() => ' . C::class . '::text("override"));'
            . 'if (!$syntax->discoveredWord()->parse("override")->success) { exit(3); }',
        );
        $this->assertSame(0, $result['status'], $result['output']);
        $this->assertStringContainsString('fixture/root:', $result['output']);
        $this->assertStringContainsString('fixture/dependency:', $result['output']);
    } finally {
        $project->clean();
    }
});
