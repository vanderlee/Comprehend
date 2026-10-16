<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\PHPStan;

use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Reflection\ReflectionProvider;
use Vanderlee\Comprehend\Comprehend;
use Vanderlee\Comprehend\Syntax;
use Vanderlee\Comprehend\Syntax\Catalog;

/** Built-in Syntax factories have the same signatures as static facade factories. */
final class SyntaxMethodsExtension implements MethodsClassReflectionExtension
{
    private readonly ReflectionProvider $reflectionProvider;

    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->reflectionProvider = $reflectionProvider;
    }

    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        return $classReflection->getName() === Syntax::class && Catalog::builtinRule($methodName) !== null;
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        return $this->reflectionProvider->getClass(Comprehend::class)->getMethod($methodName, new OutOfClassScope());
    }
}
