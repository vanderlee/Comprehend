<?php

namespace Vanderlee\Comprehend\Parser\Output;

/**
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
    private $assignCallbacks = [];

    /**
     * Resolve all callbacks registered to this trait
     *
     * @param $text
     */
    private function resolveAssignCallbacks($text)
    {
        foreach ($this->assignCallbacks as $callback) {
            $callback($text);
        }
    }

    /**
     * After parsing, assign the matched input to the specified local variable.
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
