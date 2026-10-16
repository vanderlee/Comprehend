<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\PHPStan;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use Vanderlee\Comprehend\Comprehend;

/** Preserves an existing facade's value type when attaching it to a vocabulary. */
final class ValueTypeExtension implements DynamicMethodReturnTypeExtension, DynamicStaticMethodReturnTypeExtension
{
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
        return strtolower($methodReflection->getName()) === 'of';
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
        return $this->project(array_values($methodCall->getArgs()), $scope);
    }

    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope,
    ): Type {
        return $this->project(array_values($methodCall->getArgs()), $scope);
    }

    /** @param list<Arg> $args */
    private function project(array $args, Scope $scope): Type
    {
        $value = new MixedType();
        foreach ($args as $index => $arg) {
            if ($arg->unpack) {
                break;
            }
            $original = $arg->getAttribute('originalArg');
            $name = $original instanceof Arg ? $original->name : $arg->name;
            if (($name === null && $index === 0) || $name?->toString() === 'parser') {
                $type = $scope->getType($arg->value);
                if ((new ObjectType(Comprehend::class))->isSuperTypeOf($type)->yes()) {
                    $value = $type->getTemplateType(Comprehend::class, 'T');
                }
                break;
            }
        }

        return new GenericObjectType(Comprehend::class, [$value]);
    }
}
