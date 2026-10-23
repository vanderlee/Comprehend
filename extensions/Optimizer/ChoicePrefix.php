<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Optimizer;

use Vanderlee\Comprehend\Match\Failure;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/** @internal Bounded, source-free compressed paths for long shared ASCII literals. */
final readonly class ChoicePrefix
{
    private const MAX_ALTERNATIVES = 256;
    private const MAX_TOTAL_BYTES = 262144;
    private const MAX_DEPTH = 32;
    private const MIN_SHARED_BYTES = 32;

    /**
     * @param list<array{?int, int, string}> $nodes Parent, byte offset and compressed edge.
     * @param array<array-key, int> $terminals
     * @param array<array-key, array{string, string}> $diagnostics
     */
    private function __construct(private array $nodes, private array $terminals, private array $diagnostics)
    {
        // Preparation retains only strings and relative coordinates, never execution state.
    }

    public static function prepare(Choice $choice): ?self
    {
        $literals = [];
        $diagnostics = [];
        $bytes = 0;
        foreach ($choice as $index => $parser) {
            if (
                $parser::class !== Text::class || $parser->hasExecutableMetadata()
                || $parser->getCaseSensitivity() === false
            ) {
                return null;
            }
            $literal = $parser->getText();
            $bytes += strlen($literal);
            if (
                strlen($literal) <= Preparation::MAX_LOOKAHEAD
                || strlen($literal) > Preparation::MAX_LITERAL_BYTES
                || $bytes > self::MAX_TOTAL_BYTES || count($literals) >= self::MAX_ALTERNATIVES
                || preg_match('/[\x80-\xff]/', $literal) !== 0
            ) {
                return null;
            }
            $literals[$index] = $literal;
            $diagnostics[$index] = [(string) $parser, $parser->graphLabel()];
        }
        if (count($literals) < 4) {
            return null;
        }
        $prefix = reset($literals);
        foreach ($literals as $literal) {
            $prefix = substr($prefix, 0, strspn($prefix ^ $literal, "\0"));
        }
        // Short literals already have lexical tables; diverse initials have dispatch.
        if (strlen($prefix) < self::MIN_SHARED_BYTES) {
            return null;
        }
        $nodes = [];
        $terminals = [];
        if (!self::build($literals, 0, null, 0, $nodes, $terminals)) {
            return null;
        }

        // Two ordinary attempts avoid planning costs for common early successes.
        foreach (array_slice(array_keys($literals), 0, 2) as $index) {
            unset($terminals[$index]);
        }

        return new self($nodes, $terminals, $diagnostics);
    }

    /**
     * @param array<array-key, string> $literals
     * @param list<array{?int, int, string}> $nodes
     * @param array<array-key, int> $terminals
     */
    private static function build(
        array $literals,
        int $offset,
        ?int $parent,
        int $depth,
        array &$nodes,
        array &$terminals,
    ): bool {
        if ($literals === [] || $depth >= self::MAX_DEPTH || count($nodes) >= Preparation::MAX_LITERAL_NODES) {
            return false;
        }
        $edge = substr(array_values($literals)[0], $offset);
        foreach ($literals as $literal) {
            $part = substr($literal, $offset, strlen($edge));
            $edge = substr($edge, 0, strspn($edge ^ $part, "\0"));
        }
        $id = count($nodes);
        $nodes[] = [$parent, $offset, $edge];
        $offset += strlen($edge);
        $groups = [];
        foreach ($literals as $index => $literal) {
            if (strlen($literal) === $offset) {
                $terminals[$index] = $id;
            } else {
                $groups[ord($literal[$offset])][$index] = $literal;
            }
        }
        foreach ($groups as $group) {
            if (!self::build($group, $offset, $id, $depth + 1, $nodes, $terminals)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<int, int|null> $examined Per-recognition edge outcomes; null means matched. */
    public function reject(
        int|string $index,
        Source $source,
        int $offset,
        ParseSession $session,
        array &$examined,
    ): ?Failure {
        if (!isset($this->terminals[$index])) {
            return null;
        }
        $progress = $this->examine($this->terminals[$index], $source, $offset, $examined);
        if ($progress === null) {
            return null;
        }
        [$expectation, $rule] = $this->diagnostics[$index];
        $session->consumeWork($progress + 2, $offset);
        $session->recordFailure($offset + $progress, $expectation, [[...$session->rulePath(), $rule]]);
        $failure = new Failure($progress);
        $failure->setSourceMetadata($source->span($offset, $offset), $session->farthestFailure(true));

        return $failure;
    }

    /** @param array<int, int|null> $examined */
    private function examine(int $id, Source $source, int $origin, array &$examined): ?int
    {
        if (array_key_exists($id, $examined)) {
            return $examined[$id];
        }
        [$parent, $offset, $edge] = $this->nodes[$id];
        if ($parent !== null) {
            $progress = $this->examine($parent, $source, $origin, $examined);
            if ($progress !== null) {
                return $examined[$id] = $progress;
            }
        }
        $start = $origin + $offset;
        $part = $source->sliceBytes(
            min($start, $source->byteLength()),
            min($start + strlen($edge), $source->byteLength()),
        );
        $matched = $part === $edge ? strlen($edge) : strspn($part ^ $edge, "\0");

        return $examined[$id] = $matched === strlen($edge) ? null : $offset + $matched;
    }
}
