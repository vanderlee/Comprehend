<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Recovery;

use LogicException;
use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Builder\NullabilityLookup;
use Vanderlee\Comprehend\Builder\ReusableSnapshot;
use Vanderlee\Comprehend\Directive\UnaryDirective;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/** @internal An item must occupy a complete field, without consuming its list boundary or trivia. */
final class BoundedItem extends UnaryDirective implements ReusableSnapshot
{
    use ExactClassSnapshotReuseTrait;

    public function __construct(Parser $parser, private Parser $boundary)
    {
        parent::__construct($parser);
    }

    public function __toString(): string
    {
        return 'list item(' . $this->parser . ')';
    }

    public function children(): array
    {
        return [$this->parser, $this->boundary];
    }

    public function analysisProgressParsers(): array
    {
        return [$this->boundary];
    }

    public function analysisLeadingParsers(NullabilityLookup $lookup): array
    {
        return $this->children();
    }

    protected function copyDependencies(GraphSnapshot $context): void
    {
        parent::copyDependencies($context);
        $this->boundary = $context->copy($this->boundary);
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        if ($this->boundaryAt($source, $byteOffset, $session)) {
            return $this->failure($source, $byteOffset);
        }
        $match = $this->parser->recognizeInSession($source, $byteOffset, $session);
        if (!$match instanceof Success) {
            return $match;
        }
        $end = $byteOffset + $match->length;
        $diagnostics = $session->diagnosticState();
        try {
            $skip = $session->skipSpacing($source, $end);
            $complete = $skip !== false && $this->boundaryAt($source, $end + $skip, $session);
        } finally {
            $session->restoreDiagnostics($diagnostics);
        }
        if ($complete) {
            return $match;
        }
        $session->recordFailure($end, 'list separator or closing delimiter');

        return $this->failure($source, $byteOffset, $match->length);
    }

    private function boundaryAt(Source $source, int $offset, ParseSession $session): bool
    {
        $match = RecoveryContext::attempt($this->boundary, $source, $offset, $session);
        if (!$match instanceof Success || $match->recoveries() !== []) {
            return false;
        }
        if ($match->length === 0) {
            throw new LogicException('Recovery item boundaries must consume input');
        }

        return true;
    }
}
