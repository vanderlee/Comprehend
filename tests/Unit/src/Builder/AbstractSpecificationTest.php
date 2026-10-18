<?php

declare(strict_types=1);

namespace Tests\Unit\src\Builder;

use Tests\Fixtures\ExampleSpecification;
use Vanderlee\Comprehend\Builder\AbstractSpecification;
use Vanderlee\Comprehend\Comprehend as C;

test('specifications select their declared root and retain named rules', function (): void {
    $specification = new ExampleSpecification();

    expect($specification->freeze()->parse('a!')->success)->toBeTrue();
    expect($specification->freeze()->parse('a')->success)->toBeFalse();
    expect($specification->rule('alternate')->parse('b')->success)->toBeTrue();
});

test('constructor overrides reach root references without changing other instances', function (): void {
    $specification = new ExampleSpecification(['item' => static fn(): C => C::text('x')]);

    expect($specification->freeze()->parse('x!')->success)->toBeTrue();
    expect($specification->freeze()->parse('a!')->success)->toBeFalse();
    expect((new ExampleSpecification())->freeze()->parse('a!')->success)->toBeTrue();
});

test('callers can override the root independently of named productions', function (): void {
    $specification = new ExampleSpecification([AbstractSpecification::ROOT => C::text('custom')]);

    expect($specification->freeze()->parse('custom')->success)->toBeTrue();
    expect($specification->rule('entry')->parse('a!')->success)->toBeTrue();
});

test('frozen specifications retain their rules after builder changes', function (): void {
    $specification = new ExampleSpecification();
    $frozen = $specification->freeze();
    $specification->define('item', C::text('changed'));

    expect($frozen->parse('a!')->success)->toBeTrue();
    expect($specification->freeze()->parse('changed!')->success)->toBeTrue();
});
