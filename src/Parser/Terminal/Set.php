<?php

namespace Vanderlee\Comprehend\Parser\Terminal;

use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * Description of SetParser.
 *
 * @author Martijn
 */
class Set extends Parser
{
    use CaseSensitiveTrait;

    private $set = null;

    /**
     * Match only characters inside the set (`true`) or outside (`false`).
     *
     * @var bool
     */
    private $include = true;

    /**
     * Match any single character in the set or not in the set.
     *
     * @param string $set
     * @param bool   $include Set to false to match only characters NOT in the set
     *
     * @throws \Exception
     */
    public function __construct($set, $include = true)
    {
        if (mb_strlen($set) <= 0) {
            throw new \InvalidArgumentException('Empty set');
        }

        $this->set = count_chars($set, 3);
        $this->include = (bool) $include;
    }

    protected function parse(&$input, $offset, Context $context)
    {
        if ($offset >= mb_strlen($input)) {
            return $this->failure($input, $offset);
        }

        $this->pushCaseSensitivityToContext($context);

        if (strstr($context->handleCase($this->set), $context->handleCase($input[$offset])) !== false) {
            $this->popCaseSensitivityFromContext($context);

            return $this->makeMatch($this->include, $input, $offset, 1);
        }

        $this->popCaseSensitivityFromContext($context);

        return $this->makeMatch(!$this->include, $input, $offset, 1);
    }

    public function __toString()
    {
        return ($this->include
                ? ''
                : chr(0xAC)).'( \''.implode('\' | \'', str_split($this->set)).'\' )';
    }
}
