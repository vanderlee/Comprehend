<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Optimizer\Parser;

use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Tree\ConcreteNode;

/** @internal Exact prepared combinators accept syntax-only trivia; subclasses retain their hooks. */
trait SyntaxSpacingTrait
{
    protected function skipSpacing(
        Source $source,
        int $offset,
        ParseSession $session,
        Success|ConcreteNode|null &$syntax,
    ): int|false {
        return $this::class === self::class
            ? $session->skipSpacingSyntax($source, $offset, $syntax)
            : parent::skipSpacing($source, $offset, $session, $syntax);
    }
}
