<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Binding;

use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\MixedType;
use Vanderlee\Comprehend\Comprehend;
use Vanderlee\Comprehend\Extensions\Binding;
use Vanderlee\Comprehend\Syntax;

/** Analysis-only authoring support, enabled with Binding's separate PHPStan configuration. */
final class MethodsExtension implements MethodsClassReflectionExtension
{
    private readonly ReflectionProvider $reflectionProvider;

    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->reflectionProvider = $reflectionProvider;
    }

    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        if (!in_array($classReflection->getName(), [Comprehend::class, Syntax::class], true)) {
            return false;
        }
        $extension = new Binding();
        $factories = $classReflection->getName() === Syntax::class
            ? $extension->rules()
            : [...$extension->rules(), ...$extension->fluents()];
        foreach ($factories as $name => $factory) {
            if (strtolower($name) === strtolower($methodName)) {
                return true;
            }
        }

        return false;
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        $value = $classReflection->getActiveTemplateTypeMap()->getType('T') ?? new MixedType();

        return $this->reflectionProvider->getClass(Authoring::class)->withTypes([$value])
            ->getMethod($methodName, new OutOfClassScope());
    }
}
