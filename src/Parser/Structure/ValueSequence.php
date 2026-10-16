<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Structure;

use InvalidArgumentException;
use LogicException;
use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Builder\ReusableSnapshot;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;

/** Recognizes every operand while returning only the selected operand's lazy value. */
class ValueSequence extends Sequence implements ReusableSnapshot
{
    use ExactClassSnapshotReuseTrait;

    /** $valueIndex is a zero-based operand position, independent of concrete trivia children. */
    public function __construct(private readonly int $valueIndex, Parser ...$parsers)
    {
        parent::__construct(...$parsers);
        if ($this->analysisError() !== null) {
            throw new InvalidArgumentException('Selected value position is outside sequence operands');
        }
    }

    public function analysisError(): ?string
    {
        return $this->valueIndex < 0 || $this->valueIndex >= count($this->parsers)
            ? 'Selected value position is outside sequence operands'
            : null;
    }

    protected function prepareSequenceValue(Success $success, array $children): void
    {
        $selected = $children[$this->valueIndex] ?? null;
        if ($selected === null) {
            throw new LogicException('Selected value position is outside sequence operands');
        }
        // Always forward the child's value, including raw text from its narrower source span.
        $success->setValueMapper(static fn(): mixed => $selected->getValue());
    }
}
