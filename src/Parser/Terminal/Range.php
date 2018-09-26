<?php

namespace Vanderlee\Comprehend\Parser\Terminal;

use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * Description of RangeParser
 *
 * @author Martijn
 */
class Range extends Parser
{

    use CaseSensitiveTrait;

    private $first = null;
    private $last  = null;
    private $in    = true;

    public function __construct($first, $last, $in = true)
    {
        if ($first === null && $last === null) {
            throw new \InvalidArgumentException('Empty arguments');
        }

        $this->first = $first === null ? null : self::parseCharacter($first);
        $this->last  = $last === null ? null : self::parseCharacter($last);
        $this->in    = (bool)$in;
    }

    protected function parse(&$input, $offset, Context $context)
    {
        if ($offset >= mb_strlen($input)) {
            return $this->failure($input, $offset);
        }

        $this->pushCaseSensitivityToContext($context);

        $first = ord($context->handleCase($this->first));
        $last  = ord($context->handleCase($this->last));
        $ord   = ord($context->handleCase($input[$offset]));
        if ($first <= $ord && ($this->last === null || $ord <= $last)) {
            $this->popCaseSensitivityFromContext($context);

            return $this->in ? $this->success($input, $offset, 1) : $this->failure($input, $offset);
        }

        $this->popCaseSensitivityFromContext($context);

        return $this->in ? $this->failure($input, $offset) : $this->success($input, $offset, 1);
    }

    public function __toString()
    {
        return sprintf('x%02x-x%02x', ord($this->first), ord($this->last));
    }

}
