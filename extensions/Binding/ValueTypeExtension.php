<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Binding;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Accessory\AccessoryArrayListType;
use PHPStan\Type\Accessory\NonEmptyArrayType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use Vanderlee\Comprehend\Comprehend;
use Vanderlee\Comprehend\Syntax;

/** Infers value projections whose magic factory and fluent signatures differ. */
final class ValueTypeExtension implements DynamicMethodReturnTypeExtension, DynamicStaticMethodReturnTypeExtension
{
    private const METHODS = [
        'manyvalues', 'somevalues', 'repeatvalues', 'separatedby', 'optionalvalue',
        'collect', 'valuechoice', 'ignorethen',
    ];

    /** @var class-string */
    private readonly string $className;

    /** @param class-string $className */
    public function __construct(string $className = Comprehend::class)
    {
        $this->className = $className;
    }

    public function getClass(): string
    {
        return $this->className;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return in_array(strtolower($methodReflection->getName()), self::METHODS, true);
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return $this->isMethodSupported($methodReflection);
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope,
    ): Type {
        $fluent = $this->className !== Syntax::class;

        return $this->project(
            strtolower($methodReflection->getName()),
            array_values($methodCall->getArgs()),
            $scope,
            $fluent ? $this->valueType($scope->getType($methodCall->var)) : null,
        );
    }

    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope,
    ): Type {
        return $this->project(
            strtolower($methodReflection->getName()),
            array_values($methodCall->getArgs()),
            $scope,
            null,
        );
    }

    /** @param list<Arg> $args */
    private function project(string $name, array $args, Scope $scope, ?Type $receiver): Type
    {
        // An unpacked argument list cannot reliably be assigned to operands or bounds here.
        foreach ($args as $arg) {
            if ($arg->unpack) {
                return new GenericObjectType(Comprehend::class, [new MixedType()]);
            }
        }
        $item = $receiver ?? $this->valueType($this->argument($args, 0, ['parser', 'item'], $scope));
        if ($name === 'collect' || $name === 'valuechoice') {
            $values = $receiver === null ? [] : [$receiver];
            foreach ($args as $arg) {
                $values[] = $this->valueType($scope->getType($arg->value));
            }
            if ($name === 'collect') {
                $builder = ConstantArrayTypeBuilder::createEmpty();
                foreach ($values as $value) {
                    $builder->setOffsetValueType(null, $value);
                }
                $value = $builder->getArray();
            } else {
                $value = $values === [] ? new MixedType() : TypeCombinator::union(...$values);
            }
        } elseif ($name === 'optionalvalue') {
            $default = $this->argument($args, $receiver === null ? 1 : 0, ['default'], $scope, new NullType());
            $value = TypeCombinator::union($item, $default);
        } elseif ($name === 'ignorethen') {
            $value = $this->valueType($this->argument($args, 0, ['next'], $scope));
        } else {
            $value = TypeCombinator::intersect(new ArrayType(new IntegerType(), $item), new AccessoryArrayListType());
            if ($name === 'somevalues') {
                $value = TypeCombinator::intersect($value, new NonEmptyArrayType());
            }
        }

        return new GenericObjectType(Comprehend::class, [$value]);
    }

    private function valueType(Type $type): Type
    {
        if ($type->isString()->yes()) {
            return new StringType();
        }

        return (new ObjectType(Comprehend::class))->isSuperTypeOf($type)->yes()
            ? $type->getTemplateType(Comprehend::class, 'T')
            : new MixedType();
    }

    /**
     * @param list<Arg> $args
     * @param list<string> $names
     */
    private function argument(array $args, int $position, array $names, Scope $scope, ?Type $default = null): Type
    {
        foreach ($args as $index => $arg) {
            // PHPStan normalizes named arguments against the variadic magic signature.
            // Recover their original names to distinguish factory operands from defaults.
            $original = $arg->getAttribute('originalArg');
            $name = $original instanceof Arg ? $original->name : $arg->name;
            if (
                ($name === null && $index === $position)
                || ($name !== null && in_array($name->toString(), $names, true))
            ) {
                return $scope->getType($arg->value);
            }
        }

        return $default ?? new MixedType();
    }
}
