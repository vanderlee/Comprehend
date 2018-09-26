<?php

namespace Vanderlee\Comprehend\Directive;

use Vanderlee\Comprehend\Core\ArgumentsTrait;
use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * Description of CaseDirective
 *
 * @author Martijn
 */
class CaseSensitive extends Parser
{

    use ArgumentsTrait;

    /**
     * @var Parser
     */
    private $parser = null;

    /**
     * @var bool
     */
    private $sensitivity = null;

    /**
     *
     * @param Parser|string|integer $parser
     * @param bool $sensitivity
     */
    public function __construct($sensitivity, $parser)
    {
        $this->parser      = self::getArgument($parser);
        $this->sensitivity = (bool)$sensitivity;
    }

    protected function parse(&$input, $offset, Context $context)
    {
        $context->pushCaseSensitivity($this->sensitivity);
        $match = $this->parser->parse($input, $offset, $context);
        $context->popCaseSensitivity();

        return $match;
    }

    public function __toString()
    {
        return ($this->sensitivity ? 'case' : 'no-case') . '( ' . $this->parser . ' )';
    }

}
