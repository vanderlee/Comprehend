<?php

namespace vanderlee\comprehend\parser\terminal;

use \vanderlee\comprehend\parser\Parser;
use \vanderlee\comprehend\core\Context;

/**
 * Description of SetParser
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
     * @param bool $include Set to false to match only characters NOT in the set
     * @throws \Exception
     */
    public function __construct(string $set, $include = true)
    {
        if (mb_strlen($set) <= 0) {
            throw new \InvalidArgumentException('Empty set');
        }

        $this->set = count_chars($set, 3);
        $this->include = (bool)$include;
    }

    protected function parse(&$input, $offset, Context $context)
    {

        if ($offset >= mb_strlen($input)) {
            return $this->failure($input, $offset);
        }

        $this->pushCaseSensitivityToContext($context);

        if (strchr($context->handleCase($this->set), $context->handleCase($input[$offset])) !== FALSE) {
            $this->popCaseSensitivityFromContext($context);

            return $this->makeMatch($this->include, $input, $offset, 1);
        }

        $this->popCaseSensitivityFromContext($context);

        return $this->makeMatch(!$this->include, $input, $offset, 1);
    }

    public function __toString()
    {
        return ($this->include ? '' : chr(0xAC)) . '( \'' . join('\' | \'', str_split($this->set)) . '\' )';
    }

}
