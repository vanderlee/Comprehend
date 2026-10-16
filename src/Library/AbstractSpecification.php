<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Library;

use Vanderlee\Comprehend\Builder\AbstractRuleset;
use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Parser\Parser;

/** Installs a specification's named rules and caller overrides on the shared graph. */
abstract class AbstractSpecification extends AbstractRuleset
{
    /** @var string */
    protected const ROOT_RULE = '';

    /** @param array<string, C|Parser|Definition|callable> $overwrites */
    public function __construct(array $overwrites = [])
    {
        $separator = strrpos(static::class, '\\');
        $name = $separator === false ? static::class : substr(static::class, $separator + 1);
        parent::__construct(array_replace(
            $this->definitions(),
            [self::ROOT => $this->rule(static::ROOT_RULE)],
            $overwrites,
        ), $name);
    }

    /** @return array<string, C|Parser|Definition|callable> */
    abstract protected function definitions(): array;
}
