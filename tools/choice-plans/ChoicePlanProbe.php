<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Tools\ChoicePlans;

use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Failure;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/**
 * Research only: a byte trie or compressed prefix tree classifies flat ASCII Text
 * alternatives. The ordinary Choice loop still selects and merges diagnostics in
 * order. Successful branches use their original recognizers and CSTs. This is an
 * internal prefix plan, not a rewrite of the authored grammar into new sequences.
 */
final class ChoicePlanProbe extends Choice
{
    /** @var list<array{string, list<int>, array<int, int>, int, int}> */
    private array $nodes = [];
    /** @var list<int> DFS order makes rejected subtrees contiguous without per-prefix member lists. */
    private array $leaves = [];
    /** @var array<int, int|null> Null means the literal matched completely. */
    private array $progress = [];
    private int $maxBytes;

    /** @param list<string> $literals */
    public function __construct(private array $literals, private bool $factored)
    {
        parent::__construct(...array_map(static fn(string $text): Text => new Text($text), $literals));
        $this->maxBytes = max(array_map(strlen(...), $literals));
        $this->build(array_keys($literals), 0, root: true);
    }

    public function concreteKind(): string
    {
        return Choice::class;
    }

    protected function diagnosticName(): string
    {
        return 'Choice';
    }

    public function planNodes(): int
    {
        return count($this->nodes);
    }

    /** @param list<int> $indices */
    private function build(array $indices, int $offset, bool $root = false): int
    {
        $edge = '';
        if (!$root) {
            $edge = substr($this->literals[$indices[0]], $offset, $this->factored ? null : 1);
            if ($this->factored) {
                foreach ($indices as $index) {
                    $other = substr($this->literals[$index], $offset, strlen($edge));
                    $edge = substr($edge, 0, strspn($edge ^ $other, "\0"));
                }
            }
        }
        $id = count($this->nodes);
        $this->nodes[] = ['', [], [], 0, 0];
        $start = count($this->leaves);
        $offset += strlen($edge);
        $terminals = [];
        $groups = [];
        foreach ($indices as $index) {
            if (strlen($this->literals[$index]) === $offset) {
                $terminals[] = $index;
                $this->leaves[] = $index;
            } else {
                $groups[ord($this->literals[$index][$offset])][] = $index;
            }
        }
        $children = [];
        foreach ($groups as $byte => $group) {
            $children[$byte] = $this->build($group, $offset);
        }
        $this->nodes[$id] = [$edge, $terminals, $children, $start, count($this->leaves)];

        return $id;
    }

    private function rejectSubtree(int $node, int $progress): void
    {
        for ($i = $this->nodes[$node][3]; $i < $this->nodes[$node][4]; ++$i) {
            $this->progress[$this->leaves[$i]] = $progress;
        }
    }

    private function classify(int $id, string $subject, int $offset): void
    {
        [$edge, $terminals, $children] = $this->nodes[$id];
        $part = substr($subject, $offset, strlen($edge));
        $matched = $part === $edge ? strlen($edge) : strspn($part ^ $edge, "\0");
        if ($matched < strlen($edge)) {
            $this->rejectSubtree($id, $offset + $matched);
            return;
        }
        $offset += $matched;
        foreach ($terminals as $index) {
            $this->progress[$index] = null;
        }
        $next = isset($subject[$offset]) ? ord($subject[$offset]) : -1;
        foreach ($children as $byte => $child) {
            if ($byte === $next) {
                $this->classify($child, $subject, $offset);
            } else {
                $this->rejectSubtree($child, $offset);
            }
        }
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $previous = $this->progress;
        $this->progress = [];
        try {
            if (!$session->hasRecognitionLimits() && !$session->hasActiveSpacing() && $session->isCaseSensitive()) {
                $subject = $source->sliceBytes($byteOffset, min($source->byteLength(), $byteOffset + $this->maxBytes));
                $this->classify(0, $subject, 0);
            }

            return parent::recognize($source, $byteOffset, $session);
        } finally {
            $this->progress = $previous;
        }
    }

    protected function attemptAlternative(
        int|string $index,
        Parser $parser,
        Source $source,
        int $offset,
        ParseSession $session,
    ): AbstractMatch {
        $progress = $this->progress[$index] ?? null;
        if ($progress === null) {
            return $parser->recognizeInSession($source, $offset, $session);
        }
        $session->consumeWork($progress + 2, $offset);
        $session->recordFailure(
            $offset + $progress,
            (string) $parser,
            [[...$session->rulePath(), $parser->graphLabel()]],
        );
        $failure = new Failure($progress);
        $failure->setSourceMetadata($source->span($offset, $offset), $session->farthestFailure(true));

        return $failure;
    }

    /** @return array<mixed> */
    public static function fingerprint(ParseResult $result): array
    {
        return [$result->success, $result->consumedByteLength, $result->farthestFailure,
            $result->diagnostic?->expected, $result->diagnostic?->rulePaths,
            $result->node?->toArray(), $result->ast()?->toArray(), $result->value(), $result->results()];
    }
}
