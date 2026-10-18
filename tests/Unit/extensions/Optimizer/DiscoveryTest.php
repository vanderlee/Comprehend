<?php

declare(strict_types=1);

namespace Tests\Extensions\Optimizer;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\GraphOptimization;
use RuntimeException;
use Tests\Fixtures\Syntax\DiscoveryProject;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Optimizer\GraphOptimizer;
use Vanderlee\Comprehend\Syntax\ExtensionLoader;

uses(TestCase::class);

test('optimizer capability needs no syntax registrations and caches its provider', function (): void {
    $manifest = ['extra' => ['comprehend' => ['extensions' => [
        ['providers' => [GraphOptimizer::class => [GraphOptimization::class]]],
    ]]]];
    $loader = new ExtensionLoader(['fixture/optimizer' => $manifest]);
    $this->assertSame([], $loader->names());
    $optimizer = $loader->requireOne(GraphOptimization::class);
    $this->assertInstanceOf(GraphOptimizer::class, $optimizer);
    $this->assertSame($optimizer, $loader->requireOne(GraphOptimization::class));
});

test('invalid or absent optimizers fail only when requested', function (array $entries): void {
    $project = new DiscoveryProject();
    try {
        $manifest = ['extra' => ['comprehend' => ['extensions' => $entries]]];
        $result = $project->run(
            '$loader = new ' . ExtensionLoader::class
            . '(' . var_export(['fixture/optimizer' => $manifest], true) . ');'
            . '(new ReflectionProperty(' . var_export(ExtensionLoader::class, true)
            . ', "default"))->setValue(null, $loader);'
            . '$parser = ' . C::class . '::text("a")->many();'
            . 'if (!$parser->freeze(0)->parse("aaa")->success) { exit(2); }'
            . 'try { $parser->freeze(); exit(3); } catch (RuntimeException $error) { echo $error->getMessage(); }',
        );
        $this->assertSame(0, $result['status'], $result['output']);
        $this->assertStringContainsString(strtolower(GraphOptimization::class), strtolower($result['output']));
    } finally {
        $project->clean();
    }
})->with([
    [[]],
    [[['providers' => ['MissingOptimizerProvider' => [GraphOptimization::class]]]]],
    [[['providers' => [\stdClass::class => [GraphOptimization::class]]]]],
]);

test('conflicting discovered optimizers report package and file context', function (): void {
    $project = new DiscoveryProject();
    try {
        $project->write('extensions/Other.extension.json', [
            'providers' => [GraphOptimizer::class => [GraphOptimization::class]],
        ]);
        $result = $project->run(
            '$parser = ' . C::class . '::text("a");'
            . 'if (!$parser->freeze(0)->parse("a")->success) { exit(2); }'
            . 'try { $parser->freeze(); exit(3); } catch (RuntimeException $error) { echo $error->getMessage(); }',
        );
        $this->assertSame(0, $result['status'], $result['output']);
        $this->assertStringContainsString('Ambiguous extension capability', $result['output']);
        $this->assertStringContainsString('fixture/root', $result['output']);
        $this->assertStringContainsString('Other.extension.json', $result['output']);
    } finally {
        $project->clean();
    }
});

test('core combinators contain ordinary recognition only', function (): void {
    foreach (['Repeat', 'Choice', 'Not'] as $name) {
        $source = file_get_contents(dirname(__DIR__, 4) . '/src/Parser/Structure/' . $name . '.php');
        $this->assertDoesNotMatchRegularExpression(
            '/Optimizer|LexicalTable|ReadRun|prepareLexicalRecognition|withOptimizedRecognition/',
            $source,
        );
    }
});
