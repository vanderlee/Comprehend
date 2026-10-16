<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Expression\Parser;

use Closure;
use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\SeparatedList;
use Vanderlee\Comprehend\Result\ParseResult;

/** @internal Projects operators and operands while reusing list spacing, bounds and rollback. */
final class ReductionList extends SeparatedList
{
    use ExactClassSnapshotReuseTrait;

    /** @var Closure(list<ParseResult>): mixed */
    private readonly Closure $reduce;

    /** @param callable(list<ParseResult>): mixed $reduce */
    public function __construct(
        callable $reduce,
        Parser $item,
        Parser $separator,
        ?int $max = null,
        bool $allowLeading = false,
        bool $allowTrailing = false,
    ) {
        parent::__construct($item, $separator, 1, $max, $allowLeading, $allowTrailing);
        $this->reduce = Closure::fromCallable($reduce);
    }

    protected function prepareListValue(Success $success, array $items, array $selected): void
    {
        $reduce = $this->reduce;
        $success->setValueMapper(static fn(): mixed => $reduce(array_map(
            static fn(Success $child): ParseResult => new ParseResult($child),
            $selected,
        )));
    }
}
