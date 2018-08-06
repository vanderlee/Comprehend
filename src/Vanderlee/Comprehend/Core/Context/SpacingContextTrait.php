<?php

namespace vanderlee\comprehend\core\context;

use vanderlee\comprehend\core\ArgumentsTrait;
use \vanderlee\comprehend\parser\Parser;

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
        $this->spacers[] = ($skipper === null || $skipper === true) ? $skipper : $this->getArgument($skipper);
    }

    public function popSpacer()
    {
        array_pop($this->spacers);
    }

    public function skipSpacing($in, $offset)
    {
        $skipper = end($this->spacers);

        // If `true`; use top-most Parser instance in stack.
        if ($skipper === true) {
            do {
                $skipper = prev($this->spacers);
            } while ($skipper !== false && !($skipper instanceof Parser));
        }

        if ($skipper instanceof Parser) {
            $match = $skipper->match($in, $offset);
            if ($match->match) {
                return $match->length;
            }
        }

        return 0;
    }
}