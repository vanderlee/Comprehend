<?php

declare(strict_types=1);

namespace Tests\Extensions\Expression;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Extensions\Expression\Operator;
use Vanderlee\Comprehend\Extensions\Expression\OperatorKind;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;

uses(TestCase::class);

test('operator definitions detach mutable syntax and do not evaluate their reducer', function (): void {
    $calls = 0;
    $syntax = new Sequence(new Text('+'));
    $operator = Operator::infixLeft($syntax, 10, static function (int $left, int $right) use (&$calls): int {
        ++$calls;
        return $left + $right;
    });
    $syntax->add(new Text('+'));
    $this->assertSame(OperatorKind::LEFT, $operator->kind);
    $this->assertSame(10, $operator->precedence);
    $this->assertTrue($operator->parser()->parseAll(new ByteSource('+'))->success);
    $this->assertFalse($operator->parser()->parseAll(new ByteSource('++'))->success);
    $this->assertNotSame($operator->parser(), $operator->parser());
    $this->assertSame(0, $calls);
    $this->assertSame(3, ($operator->reduce)(1, 2));
});

test('operator factories declare their binding kind', function (string $factory, OperatorKind $kind): void {
    $operator = Operator::$factory('!', -5, static fn(int $value): int => $value);
    $this->assertSame($kind, $operator->kind);
    $this->assertSame(-5, $operator->precedence);
})->with([
    ['prefix', OperatorKind::PREFIX], ['postfix', OperatorKind::POSTFIX],
    ['infixLeft', OperatorKind::LEFT], ['infixRight', OperatorKind::RIGHT],
    ['infixNone', OperatorKind::NON_ASSOCIATIVE],
]);
