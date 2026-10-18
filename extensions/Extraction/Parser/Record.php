<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Extraction\Parser;

use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Sequence;

/** Recognizes ordered fields and lazily projects their values under the supplied keys. */
class Record extends Sequence
{
    use ExactClassSnapshotReuseTrait;

    /** @param array<array-key, Parser> $fields */
    public function __construct(array $fields)
    {
        if ($fields !== []) {
            parent::__construct(...array_values($fields));
        }
        // Unlike an ordinary sequence, an empty record intentionally recognizes empty input.
        $this->parsers = $fields;
    }

    public function add(Parser ...$arguments): static
    {
        foreach ($arguments as $parser) {
            $this->parsers[] = $parser;
        }

        return $this;
    }

    protected function prepareSequenceValue(Success $success, array $children): void
    {
        $keys = array_keys($this->parsers);
        $success->setValueMapper(static function () use ($keys, $children): array {
            $record = [];
            foreach ($children as $index => $child) {
                $record[$keys[$index]] = $child->getValue();
            }

            return $record;
        });
    }
}
