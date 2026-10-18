<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Expression\Parser;

use Closure;
use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Result\ParseResult;

/** @internal Installs a lazy projection while retaining ordinary sequence recognition and lifecycle. */
final class ReductionSequence extends Sequence
{
    use ExactClassSnapshotReuseTrait;

    /** @var Closure(list<ParseResult>): mixed */
    private readonly Closure $reduce;

    /** @param callable(list<ParseResult>): mixed $reduce */
    public function __construct(callable $reduce, Parser ...$parsers)
    {
        parent::__construct(...$parsers);
        $this->reduce = Closure::fromCallable($reduce);
    }

    protected function prepareSequenceValue(Success $success, array $children): void
    {
        $reduce = $this->reduce;
        $success->setValueMapper(static fn(): mixed => $reduce(array_map(
            static fn(Success $child): ParseResult => new ParseResult($child),
            $children,
        )));
    }
}
