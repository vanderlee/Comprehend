<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Optimizer;

use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Failure;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Tree\ConcreteTemplate;

/**
 * Classifies bounded lexical patterns through their original recognition runtime, then
 * reuses source-free syntax and diagnostic recipes. This is a bounded lexical table,
 * not position memoization. Callbacks, custom parsers and unproven lookahead execute normally.
 */
final class LexicalTable
{
    private const MAX_ENTRIES = 128;

    /** @var array<string, array{int, ConcreteTemplate|null, ?int, list<string>, list<list<string>>}> */
    private array $entries = [];

    private function __construct(private readonly Parser $parser, private readonly int $lookahead)
    {
        // Misses continue through the original typed recognition graph.
    }

    public static function prepare(Parser $parser, ?Preparation $preparation = null): ?self
    {
        $lookahead = ($preparation ?? new Preparation())->lookahead($parser)[0] ?? null;

        return $lookahead !== null && $lookahead > 0 && $lookahead <= Preparation::MAX_LOOKAHEAD
            ? new self($parser, $lookahead) : null;
    }

    /** @internal The owning combinator establishes its spacing and budget scopes first. */
    public function attempt(
        Source $source,
        int $offset,
        ParseSession $session,
        bool $retainFailureDiagnostic = true,
    ): AbstractMatch {
        $prefix = $source->sliceBytes($offset, min($source->byteLength(), $offset + $this->lookahead));
        if (preg_match('/[\x80-\xff]/', $prefix) !== 0) {
            return $this->parser->recognizeInSession($source, $offset, $session, $retainFailureDiagnostic);
        }
        $key = $source->mode()->value . ':' . (int) $session->isCaseSensitive()
            . ':' . $session->getPreference()->value . ':' . $prefix;
        $path = $session->rulePath();
        if (isset($this->entries[$key])) {
            [$length, $tree, $failure, $expected, $paths] = $this->entries[$key];
            $this->replayFailure($session, $offset, $path, $failure, $expected, $paths);
            $node = $tree?->instantiate($source, $offset);
            $match = $node === null ? new Failure($length) : new Success($length);
            $omitDiagnostic = $node === null && !$retainFailureDiagnostic;
            $diagnostic = $omitDiagnostic ? null : $session->diagnostic($node === null);
            $match->setSourceMetadata(
                $node->span ?? $source->span($offset, $offset),
                $omitDiagnostic ? $session->farthestFailure(true) : $diagnostic?->byteOffset,
                $diagnostic,
            );
            if ($node !== null) {
                $match->setConcreteNode($node);
            }

            return $match;
        }
        if (count($this->entries) >= self::MAX_ENTRIES) {
            return $this->parser->recognizeInSession($source, $offset, $session, $retainFailureDiagnostic);
        }

        $diagnostics = $session->diagnosticState();
        // Isolate the pattern's observations from failures inherited from its caller.
        $session->restoreDiagnostics([null, [], [], $diagnostics[3], null]);
        try {
            $match = $this->parser->recognizeInSession($source, $offset, $session);
            $observed = $session->diagnostic();
        } finally {
            $session->restoreDiagnostics($diagnostics);
        }
        $failure = $observed === null ? null : $observed->byteOffset - $offset;
        $expected = $observed->expected ?? [];
        $paths = array_map(
            static fn(array $observedPath): array => array_slice($observedPath, count($path)),
            $observed->rulePaths ?? [],
        );
        $this->replayFailure($session, $offset, $path, $failure, $expected, $paths);
        $omitDiagnostic = !$match->accepted && !$retainFailureDiagnostic;
        $diagnostic = $omitDiagnostic ? null : $session->diagnostic(!$match->accepted);
        if ($match->span !== null) {
            $match->setSourceMetadata(
                $match->span,
                $omitDiagnostic ? $session->farthestFailure(true) : $diagnostic?->byteOffset,
                $diagnostic,
            );
        }
        $this->entries[$key] = [$match->length,
            $match->node === null ? null : ConcreteTemplate::from($match->node, $offset), $failure, $expected, $paths];

        return $match;
    }

    /**
     * @param list<string> $path
     * @param list<string> $expected
     * @param list<list<string>> $paths
     */
    private function replayFailure(
        ParseSession $session,
        int $offset,
        array $path,
        ?int $failure,
        array $expected,
        array $paths,
    ): void {
        if ($failure === null) {
            return;
        }
        $paths = array_map(static fn(array $relative): array => [...$path, ...$relative], $paths);
        foreach ($expected ?: [null] as $expectation) {
            $session->recordFailure($offset + $failure, $expectation, $paths);
        }
    }
}
