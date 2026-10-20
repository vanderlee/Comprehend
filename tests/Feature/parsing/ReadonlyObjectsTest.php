<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Builder\FrozenGrammar;
use Vanderlee\Comprehend\Builder\NullabilityLookup;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Expression\Operator;
use Vanderlee\Comprehend\Result\Diagnostic;
use Vanderlee\Comprehend\Result\Recovery;
use Vanderlee\Comprehend\Result\ValidationIssue;
use Vanderlee\Comprehend\Result\ValidationResult;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Syntax;
use Vanderlee\Comprehend\Syntax\Catalog;
use Vanderlee\Comprehend\Tree\AstNode;
use Vanderlee\Comprehend\Tree\ConcreteNode;
use Vanderlee\Comprehend\Tree\ConcreteTemplate;

test('immutable objects reject dynamic properties', function (Closure $create): void {
    $object = $create();

    expect(function () use ($object): void {
        $object->annotation = 'external state';
    })->toThrow(Error::class, 'Cannot create dynamic property');
})->with([
    'span' => [fn() => (new ByteSource('a'))->span(0, 1)],
    'concrete node' => [fn() => new ConcreteNode('text', (new ByteSource('a'))->span(0, 1))],
    'AST node' => [fn() => new AstNode('text', null, (new ByteSource('a'))->span(0, 1))],
    'concrete template' => [fn() => ConcreteTemplate::from(
        new ConcreteNode('text', (new ByteSource('a'))->span(0, 1)),
        0,
    )],
    'recovery' => [function (): Recovery {
        $source = new ByteSource('a');

        return new Recovery($source->span(0, 1), new Diagnostic($source, 0, [], []));
    }],
    'validation issue' => [fn() => new ValidationIssue('invalid', 'Invalid input', (new ByteSource('a'))->span(0, 1))],
    'validation result' => [fn() => new ValidationResult()],
    'parse limits' => [fn() => new ParseLimits()],
    'frozen grammar' => [fn() => FrozenGrammar::from(C::text('a'))],
    'nullability lookup' => [fn() => new NullabilityLookup([])],
    'syntax' => [fn() => Syntax::create()],
    'catalog' => [fn() => new Catalog()],
    'operator' => [fn() => Operator::infixLeft('+', 1, fn(int $left, int $right): int => $left + $right)],
]);
