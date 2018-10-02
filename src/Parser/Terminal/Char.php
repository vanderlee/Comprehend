<?php

namespace Vanderlee\Comprehend\Parser\Terminal;

use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * Description of CharParser
 *
 * @author Martijn
 */
class Char extends Parser
{

    use CaseSensitiveTrait;

    private $character = null;

    /**
     * Match the specified character (`true`) or everything else (`false`).
     *
     * @var bool
     */
    private $include = true;

    public function __construct($character, $include = true)
    {
        $this->character = self::parseCharacter($character);
        $this->include = $include;
    }

    protected function parse(&$input, $offset, Context $context)
    {
        if ($offset >= mb_strlen($input)) {
            return $this->failure($input, $offset);
        }

        $this->pushCaseSensitivityToContext($context);

        if ($context->handleCase($input[$offset]) === $context->handleCase($this->character)) {
            $this->popCaseSensitivityFromContext($context);

            return $this->makeMatch($this->include, $input, $offset, 1);
        }

        $this->popCaseSensitivityFromContext($context);

        return $this->makeMatch(!$this->include, $input, $offset, 1);
    }

    public function __toString()
    {
        return '\'' . $this->character . '\'';
    }

}
