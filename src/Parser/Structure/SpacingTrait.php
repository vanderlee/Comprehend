<?php

namespace Vanderlee\Comprehend\Parser\Structure;

use Vanderlee\Comprehend\Core\ArgumentsTrait;
use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * Classes implementing this can scan
 *
 * @author Martijn
 */
trait SpacingTrait
{

    use ArgumentsTrait;

    /**
     * Parser used for scanning the text
     * @var Parser
     */
    private $spacer = false;

    private function pushSpacer(Context $context)
    {
        if ($this->spacer !== false) {
            $context->pushSpacer($this->spacer);
        }
    }

    private function popSpacer(Context $context)
    {
        if ($this->spacer !== false) {
            $context->popSpacer();
        }
    }

    /**
     * Set a spacing parser for this parser or disable or enable (if a previous
     * spacing parser is enabled) spacing parsing.
     *
     * @param Parser|bool|null $spacer
     * @return $this
     */
    public function spacing($spacer = true)
    {
        if ($spacer === true) {
            $this->spacer = true;
        } elseif ($spacer === null || $spacer === false) {
            $this->spacer = null;
        } else {
            $this->spacer = self::getArgument($spacer);
        }

        return $this;
    }

}
