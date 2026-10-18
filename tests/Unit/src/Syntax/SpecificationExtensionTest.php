<?php

declare(strict_types=1);

namespace Tests\Unit\src\Syntax;

use Tests\Fixtures\ExampleSpecification;
use Vanderlee\Comprehend\Syntax;
use Vanderlee\Comprehend\Syntax\SpecificationExtension;

test('specification factories stay lazy and construct a fresh grammar for each call', function (): void {
    ExampleSpecification::$builds = 0;
    $extension = new class extends SpecificationExtension {
        protected const PARSERS = ['sampleItem' => [ExampleSpecification::class, 'item']];
        protected const VERSIONS = ['sampleVersion' => ExampleSpecification::class];
    };
    $syntax = Syntax::create()->extend($extension);

    expect(ExampleSpecification::$builds)->toBe(0);
    expect(iterator_to_array($extension->fluents()))->toBe([]);
    $first = $syntax->sampleItem();
    $second = $syntax->sampleItem();
    expect(ExampleSpecification::$builds)->toBe(2);
    expect($first->parse('a')->success)->toBeTrue();
    expect($second->parse('a')->success)->toBeTrue();
    expect(ExampleSpecification::$builds)->toBe(2);
    expect($syntax->sampleVersion()->parse('a!')->success)->toBeTrue();
    expect($syntax->sampleVersion(rule: 'alternate')->parse('b')->success)->toBeTrue();
});

test('version root overrides preserve explicit named rule selection', function (): void {
    $extension = new class extends SpecificationExtension {
        protected const VERSIONS = ['sampleVersion' => ExampleSpecification::class];
        protected const VERSION_ROOTS = ['sampleVersion' => 'alternate'];
    };
    $syntax = Syntax::create()->extend($extension);

    expect($syntax->sampleVersion()->parse('b')->success)->toBeTrue();
    expect($syntax->sampleVersion()->parse('a!')->success)->toBeFalse();
    expect($syntax->sampleVersion(rule: 'entry')->parse('a!')->success)->toBeTrue();
});

test('an unconfigured specification extension leaves the vocabulary unchanged', function (): void {
    $extension = new class extends SpecificationExtension {
        // Keep the base class's empty factory configuration.
    };
    $syntax = Syntax::create();

    expect(iterator_to_array($extension->rules()))->toBe([]);
    expect(iterator_to_array($extension->fluents()))->toBe([]);
    expect($syntax->extend($extension))->toBe($syntax);
});
