<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Builder;

use SplObjectStorage;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Terminal;
use Vanderlee\Comprehend\Parser\Structure;
use Vanderlee\Comprehend\Directive;
use Vanderlee\Comprehend\Parser\Stub;
use LogicException;

/**
 * Copies parser graphs to isolate fluent expressions and frozen grammars from mutable builders.
 * Preserves sharing and cycles; SnapshotParticipant behavior owns dependency copying and
 * deferred resolution.
 */
final class GraphSnapshot
{
    /** Exact classes only: subclasses, deferred definitions and rule lookups may depend on external state. */
    private const REUSABLE_PARSERS = [
        Terminal\Any::class => true,
        Terminal\Char::class => true,
        Terminal\End::class => true,
        Terminal\Integer::class => true,
        Terminal\Nothing::class => true,
        Terminal\Range::class => true,
        Terminal\Regex::class => true,
        Terminal\Set::class => true,
        Terminal\Start::class => true,
        Terminal\Text::class => true,
        Structure\All::class => true,
        Structure\Choice::class => true,
        Structure\Except::class => true,
        Structure\Not::class => true,
        Structure\Repeat::class => true,
        Structure\SeparatedList::class => true,
        Structure\Sequence::class => true,
        Structure\ValueSequence::class => true,
        Directive\CaseSensitive::class => true,
        Directive\Lexeme::class => true,
        Directive\Prefer::class => true,
        Directive\Space::class => true,
        Directive\Trivia::class => true,
        Stub::class => true,
    ];

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

    public function __construct(private readonly bool $resolveReferences = true)
    {
        $this->copies = new SplObjectStorage();
        $this->snapshotting = new SplObjectStorage();
    }

    /** @internal Built-in nodes whose authoring copies own all graph dependencies. */
    public static function isReusableParser(Parser $parser): bool
    {
        return isset(self::REUSABLE_PARSERS[$parser::class]);
    }

    /** @internal Whether this copy traversed only owned, non-deferred built-in nodes. */
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
