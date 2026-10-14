<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Builder;

use ReflectionObject;
use ReflectionProperty;
use Vanderlee\Comprehend\Parser\Parser;

/** Inspect owned parser state without invoking getters, closures or magic access. */
final class GraphState
{
    /** @return list<ReflectionProperty> */
    public static function properties(object $object): array
    {
        $properties = [];
        for ($class = new ReflectionObject($object); $class !== false; $class = $class->getParentClass()) {
            foreach ($class->getProperties() as $property) {
                if (!$property->isStatic() && $property->getDeclaringClass()->getName() === $class->getName()
                    && $property->isInitialized($object)) {
                    $properties[] = $property;
                }
            }
        }
        return $properties;
    }

    public static function value(Parser $parser, string $name): mixed
    {
        foreach (self::properties($parser) as $property) {
            if ($property->getName() === $name) { return $property->getValue($parser); }
        }
        return null;
    }

    /** @return list<Parser> Includes trivia policy, even when it is not a leading edge. */
    public static function children(Parser $parser): array
    {
        if ($parser instanceof RuleReference || $parser instanceof Implementation) { return [$parser->graphTarget()]; }
        if ($parser instanceof AbstractRuleset) { return [$parser->resolveRule(AbstractRuleset::ROOT)]; }
        $children = [];
        foreach (self::properties($parser) as $property) {
            $value = $property->getValue($parser);
            if ($value instanceof Parser) { $children[] = $value; }
            elseif (is_array($value)) {
                foreach ($value as $item) { if ($item instanceof Parser) { $children[] = $item; } }
            }
        }
        return $children;
    }
}
