<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Vanderlee\Comprehend\Builder\FrozenGrammar;
use Vanderlee\Comprehend\Extensions\Library\AbstractSpecification;

/** Reuses immutable grammar snapshots across the upstream acceptance cases. */
final class SpecificationGrammars
{
    /** @var array<string, FrozenGrammar> */
    private static array $grammars = [];

    /** @param class-string<AbstractSpecification> $specification */
    public static function get(string $specification, string $rule): FrozenGrammar
    {
        $key = $specification . ':' . $rule;

        return self::$grammars[$key] ??= (new $specification())->rule($rule)->freeze();
    }
}
