<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Library;

use Vanderlee\Comprehend\Builder\AbstractRuleset;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Syntax\SyntaxExtension;

/** Named entry points into shared specification grammars; each authoring call builds a fresh graph. */
abstract class SpecificationExtension implements SyntaxExtension
{
    /** @var array<string, array{class-string<AbstractRuleset>, string}> */
    protected const PARSERS = [];

    /** @var array<string, class-string<AbstractRuleset>> Explicit versions, including historical grammars. */
    protected const VERSIONS = [];

    /** @var array<string, string> Default productions for specifications without a ROOT rule. */
    protected const VERSION_ROOTS = [];

    public function rules(): iterable
    {
        foreach (static::PARSERS as $name => [$class, $rule]) {
            yield $name => static fn(): C => (new $class())->rule($rule);
        }
        foreach (static::VERSIONS as $name => $class) {
            $defaultRule = static::VERSION_ROOTS[$name] ?? AbstractRuleset::ROOT;
            yield $name => static fn(string $rule = AbstractRuleset::ROOT): C
                => (new $class())->rule($rule === AbstractRuleset::ROOT ? $defaultRule : $rule);
        }
    }

    public function fluents(): iterable
    {
        return [];
    }
}
