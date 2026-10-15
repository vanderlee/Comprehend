<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Builder;

use ReflectionObject;
use ReflectionProperty;
use SplObjectStorage;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * Copies parser graphs to isolate fluent expressions and frozen grammars from mutable builders.
 * Preserves sharing and cycles while optionally resolving RuleReference and Implementation
 * targets.
 */
final class GraphSnapshot
{
    /**
     * @var SplObjectStorage<Parser|Definition, Parser|Definition>
     */
    private SplObjectStorage $copies;
    /**
     * @var array<string, RuleReference>
     */
    private array $references = [];

    public function __construct(private readonly bool $resolveReferences = true)
    {
        $this->copies = new SplObjectStorage();
    }

    public function copy(Parser $parser): Parser
    {
        return $this->copyObject($parser);
    }

    /**
     * @return ($object is Parser ? Parser : Definition)
     */
    private function copyObject(Parser|Definition $object): Parser|Definition
    {
        if (isset($this->copies[$object])) {
            $copy = $this->copies[$object];
            if (
                $object instanceof Parser
                && $copy instanceof Parser
            ) {
                return $copy;
            }
            if (
                $object instanceof Definition
                && $copy instanceof Definition
            ) {
                return $copy;
            }
            throw new \LogicException('Snapshot copy must preserve the parser or definition category');
        }
        if ($object instanceof RuleReference) {
            return $this->copyReference($object);
        }
        if (
            $object instanceof AbstractRuleset
            && $this->resolveReferences
        ) {
            $copy = clone $object;
            $this->copies[$object] = $copy;
            $copy->bindSnapshotRoot($this->copy($object->resolveRule(AbstractRuleset::ROOT, [], true)));

            return $copy;
        }
        $copy = (new ReflectionObject($object))->newInstanceWithoutConstructor();
        $this->copies[$object] = $copy;
        $this->copies[$copy] = $copy;
        foreach ($this->properties($object) as $property) {
            if (
                $object instanceof Implementation
                && $this->resolveReferences
                && $property->getDeclaringClass()->getName() === Implementation::class
                && $property->getName() === 'parser'
            ) {
                $property->setValue($copy, null);
                continue;
            }
            $property->setValue($copy, $this->copyValue($property->getValue($object)));
        }
        if (
            $copy instanceof Implementation
            && $this->resolveReferences
        ) {
            $copy->bindSnapshotTarget($this->copy($copy->graphTarget()));
        }

        return $copy;
    }

    /** @return list<ReflectionProperty> Initialized instance properties, including inherited private state. */
    private function properties(object $object): array
    {
        $properties = [];
        for ($class = new ReflectionObject($object); $class !== false; $class = $class->getParentClass()) {
            foreach ($class->getProperties() as $property) {
                if (
                    $property->isStatic()
                    || $property->getDeclaringClass()->getName() !== $class->getName()
                ) {
                    continue;
                }
                if ($property->isInitialized($object)) {
                    $properties[] = $property;
                }
            }
        }

        return $properties;
    }

    private function copyReference(RuleReference $reference): RuleReference
    {
        $copy = clone $reference;
        $this->copies[$reference] = $copy;
        if (!$this->resolveReferences) {
            return $copy;
        }

        $key = $this->parameterKey($reference->graphKey());
        if (isset($this->references[$key])) {
            return $this->copies[$reference] = $this->references[$key];
        }

        // Register before resolving so recursive references reuse this node.
        $this->references[$key] = $copy;
        $copy->bindSnapshotTarget($this->copy($reference->graphTarget(true)));

        return $copy;
    }

    private function parameterKey(mixed $value): string
    {
        if (is_object($value)) {
            return 'object:' . spl_object_id($value);
        }
        if (is_resource($value)) {
            return 'resource:' . get_resource_id($value);
        }
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $key => $item) {
                $parts[] = [$this->parameterKey($key), $this->parameterKey($item)];
            }

            return serialize($parts);
        }

        return serialize($value);
    }

    private function copyValue(mixed $value): mixed
    {
        if ($value instanceof Parser || $value instanceof Definition) {
            return $this->copyObject($value);
        }
        if (is_array($value)) {
            return array_map(fn($item) => $this->copyValue($item), $value);
        }

        // Closures, external services and value objects are caller-owned dependencies.
        return $value;
    }
}
