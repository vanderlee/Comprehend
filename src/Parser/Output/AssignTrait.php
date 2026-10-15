<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Output;

/**
 * Registers assignments of matched source text to caller variables on Parser. Assignments
 * become selected-match effects and run only during explicit commit.
 *
 * @author Martijn
 */
trait AssignTrait
{
    /**
     * List of result names to assign the matched text to.
     *
     * @var callable[]
     */
    private array $assignCallbacks = [];

    /**
     * On explicit commit, assign the matched input to the specified local variable.
     * Only assign if successfully matched entire parent up to root.
     *
     * @param mixed $variable
     *
     * @return $this
     */
    public function assignTo(&$variable)
    {
        $this->assignCallbacks[] = function ($text) use (&$variable) {
            $variable = $text;
        };

        return $this;
    }
}
