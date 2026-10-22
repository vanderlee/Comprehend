<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Builder;

use Closure;
use LogicException;
use Vanderlee\Comprehend\Parser\Parser;

/** Read-only estimates for one iteration of graph analysis. */
final readonly class NullabilityLookup
{
    /** @var array<int, bool|null> */
    private array $values;

    /**
     * @param array<int, bool|null> $values
     * @param (Closure(int): void)|null $recordDependency Internal analysis scheduling only.
     */
    public function __construct(array $values, private ?Closure $recordDependency = null)
    {
        $this->values = $values;
    }

    public function get(Parser $parser): ?bool
    {
        $id = spl_object_id($parser);
        if (!array_key_exists($id, $this->values)) {
            throw new LogicException('Analysis operand is outside the collected graph: ' . $parser->graphLabel());
        }
        $this->recordDependency?->__invoke($id);

        return $this->values[$id];
    }

    /** @param list<Parser> $parsers */
    public function every(array $parsers): ?bool
    {
        $unknown = false;
        foreach ($parsers as $parser) {
            $value = $this->get($parser);
            if ($value === false) {
                return false;
            }
            if ($value === null) {
                $unknown = true;
            }
        }

        return $unknown ? null : true;
    }

    /** @param list<Parser> $parsers */
    public function any(array $parsers): ?bool
    {
        $unknown = false;
        foreach ($parsers as $parser) {
            $value = $this->get($parser);
            if ($value === true) {
                return true;
            }
            if ($value === null) {
                $unknown = true;
            }
        }

        return $unknown ? null : false;
    }
}
