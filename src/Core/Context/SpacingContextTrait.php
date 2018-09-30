<?php

namespace Vanderlee\Comprehend\Core\Context;

use Vanderlee\Comprehend\Core\ArgumentsTrait;
use Vanderlee\Comprehend\Parser\Parser;

trait SpacingContextTrait
{
    use ArgumentsTrait;

    /**
     * List of spacers.
     *  -  Parser for a normal spacer.
     *  -  `null` to disable spacing.
     *  -  `true` to use the top-most spacer.
     *
     * @var Parser[]|null[]|bool[]
     */
    private $spacers = [];

    public function pushSpacer($skipper = null)
    {
        $this->spacers[] = ($skipper === null || $skipper === true)
            ? $skipper
            : $this->getArgument($skipper);
    }

    public function popSpacer()
    {
        array_pop($this->spacers);
    }

    public function skipSpacing($in, $offset)
    {
        $spacer = end($this->spacers);

        // If `true`; use top-most Parser instance in stack.
        if ($spacer === true) {
            do {
                $spacer = prev($this->spacers);
            } while ($spacer !== false && !($spacer instanceof Parser));
        }

        if ($spacer instanceof Parser) {
            $match = $spacer->match($in, $offset);
            return $match->match
                ? $match->length
                : false;
        }

        return 0;
    }
}