<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Builder;

use SplObjectStorage;
use Vanderlee\Comprehend\Parser\Parser;
use LogicException;

/**
 * Copies parser graphs to isolate fluent expressions and frozen grammars from mutable builders.
 * Preserves sharing and cycles; SnapshotParticipant behavior owns dependency copying and
 * deferred resolution.
 */
final class GraphSnapshot
{
    private bool $reusable = true;

    /**
     * @var SplObjectStorage<Parser|Definition, Parser|Definition>
     */
    private SplObjectStorage $copies;
    /**
     * @var array<string, Parser>
     */
    private array $identities = [];
    /** @var SplObjectStorage<Parser|Definition, true> Participants whose snapshot() has not returned. */
    private SplObjectStorage $snapshotting;

    public function __construct(
        private readonly bool $resolveReferences = true,
        private readonly bool $optimize = false,
    ) {
        $this->copies = new SplObjectStorage();
        $this->snapshotting = new SplObjectStorage();
    }

    /** @internal Only freezing enables recognition rewrites; authoring copies stay structural. */
    public function copyParser(Parser $parser): Parser
    {
        return $this->optimize ? (new GraphOptimizer())->replace($parser) : clone $parser;
    }

    /** @internal Explicit capability; inherited interfaces alone do not guarantee reuse safety. */
    public static function isReusableParser(Parser $parser): bool
    {
        return $parser instanceof ReusableSnapshot && $parser->supportsSnapshotReuse();
    }

    /** @internal Whether every copied participant declared snapshot reuse safe. */
    public function isReusable(): bool
    {
        return $this->reusable;
    }

    public function copy(Parser $parser): Parser
    {
        return $this->copyObject($parser);
    }

    public function copyDefinition(Definition $definition): Definition
    {
        return $this->copyObject($definition);
    }

    /** Whether deferred grammar targets should be freshly resolved in this snapshot. */
    public function resolvesReferences(): bool
    {
        return $this->resolveReferences;
    }

    /** Register before descending so shared and recursive dependencies reuse this destination. */
    public function registerCopy(Parser|Definition $original, Parser|Definition $copy): void
    {
        if ($original === $copy) {
            throw new LogicException('Snapshot copy must be detached from its original');
        }
        if (($original instanceof Parser) !== ($copy instanceof Parser)) {
            throw new LogicException('Snapshot copy must preserve the parser or definition category');
        }
        if (
            isset($this->copies[$original])
            && $this->copies[$original] !== $copy
        ) {
            throw new LogicException('Snapshot object already has a different copy');
        }
        $this->copies[$original] = $copy;
        // Already-copied dependencies may be revisited by specialized snapshot methods.
        $this->copies[$copy] = $copy;
    }

    /**
     * Register a candidate or reuse the parser already registered for this identity.
     * Include a participant-specific namespace in the identity to avoid unrelated collisions.
     */
    public function registerIdentity(Parser $original, Parser $candidate, mixed $identity): Parser
    {
        $key = $this->identityKey($identity);
        $copy = $this->identities[$key] ?? $candidate;
        $this->registerCopy($original, $copy);
        $this->identities[$key] = $copy;

        return $copy;
    }

    /** Recursively copy parser/definition values and arrays; retain caller-owned dependencies. */
    public function copyValue(mixed $value): mixed
    {
        if (
            $value instanceof Parser
            || $value instanceof Definition
        ) {
            return $this->copyObject($value);
        }
        if (is_array($value)) {
            return array_map(fn($item) => $this->copyValue($item), $value);
        }

        // Closures, external services and value objects are caller-owned dependencies.
        return $value;
    }

    /** @return ($object is Parser ? Parser : Definition) */
    private function copyObject(Parser|Definition $object): Parser|Definition
    {
        if ($this->reusable && (!$object instanceof Parser || !self::isReusableParser($object))) {
            $this->reusable = false;
        }
        if (isset($this->copies[$object])) {
            $copy = $this->copies[$object];
            if (($object instanceof Parser) !== ($copy instanceof Parser)) {
                throw new LogicException('Snapshot copy must preserve the parser or definition category');
            }

            return $copy;
        }
        if (isset($this->snapshotting[$object])) {
            throw new LogicException('Snapshot participant must register its copy before copying dependencies');
        }

        $this->snapshotting[$object] = true;
        try {
            $copy = $object->snapshot($this);
            if (
                !isset($this->copies[$object])
                || $this->copies[$object] !== $copy
            ) {
                throw new LogicException('Snapshot participant must return its registered copy');
            }

            return $copy;
        } finally {
            unset($this->snapshotting[$object]);
        }
    }

    private function identityKey(mixed $value): string
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
                $parts[] = [$this->identityKey($key), $this->identityKey($item)];
            }

            return serialize($parts);
        }

        return serialize($value);
    }
}
