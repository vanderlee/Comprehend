<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Structure;

use InvalidArgumentException;
use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Builder\ReusableSnapshot;
use Vanderlee\Comprehend\Builder\AnalyzableParser;
use Vanderlee\Comprehend\Builder\DefaultAnalysisTrait;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Builder\NullabilityLookup;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/**
 * Requires every operand to match at the same source offset. Consumes the shortest successful
 * length, allowing grammars to intersect recognition constraints.
 *
 * @author Martijn
 */
class All extends Parser implements AnalyzableParser, ReusableSnapshot
{
    use ExactClassSnapshotReuseTrait;
    use DefaultAnalysisTrait;

    /**
     * @var Parser[]
     */
    private array $parsers = [];

    public function __construct(Parser ...$arguments)
    {
        if (count($arguments) < 2) {
            throw new InvalidArgumentException('Less than 2 arguments provided');
        }
        $this->parsers = $arguments;
    }

    /**
     * @internal
     * @return array<array-key, Parser> Structural operands in declaration order.
     */
    public function getParsers(): array
    {
        return $this->parsers;
    }

    /** @return list<Parser> */
    public function analysisLeadingParsers(NullabilityLookup $lookup): array
    {
        return array_values($this->parsers);
    }

    public function analysisNullability(NullabilityLookup $lookup): ?bool
    {
        return $lookup->any(array_values($this->parsers));
    }

    protected function copyDependencies(GraphSnapshot $context): void
    {
        parent::copyDependencies($context);
        $this->parsers = array_map($context->copy(...), $this->parsers);
    }

    /** @return list<Parser> */
    public function children(): array
    {
        return array_values($this->parsers);
    }

    public function __toString(): string
    {
        return '( ' . implode(' + ', $this->parsers) . ' )';
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $length = PHP_INT_MAX;
        $matches = [];
        foreach ($this->parsers as $parser) {
            $match = $parser->recognizeInSession($source, $byteOffset, $session);
            $length = min($length, $match->length);
            if (!$match instanceof Success) {
                return $this->failure($source, $byteOffset, $length);
            }
            $matches[] = $match;
        }
        $success = $this->success($source, $byteOffset, $length, $matches);
        $success->setConcreteMatches($matches);

        return $success;
    }
}
