<?php

namespace Vanderlee\Comprehend\Parser\Output;

use Vanderlee\Comprehend\Core\Token;

/**
 *
 * @author Martijn
 */
trait TokenTrait
{

    /**
     * Name of the token
     *
     * @var null
     */
    private $tokenName = null;

    /**
     * Group to which this token belongs (mostly for standard Library tokens
     *
     * @var string|null
     */
    private $tokenGroup = null;

    /**
     * Is this token the deepest node in this tree to report a token on?
     *
     * @var bool
     */
    private $tokenIsTerminal = false;

    /**
     * @param string      $token
     * @param string|null $group
     * @param bool        $isTerminal
     *
     * @return $this
     */
    public function token($token, $group = null, $isTerminal = false)
    {
        $this->tokenName = $token;
        $this->tokenGroup = $group;
        $this->tokenIsTerminal = $isTerminal;

        return $this;
    }

    /**
     * Has a token been set for this Parser?
     *
     * @return bool
     */
    public function hasToken()
    {
        return $this->tokenName !== null;
    }

    private function resolveToken(&$input, $offset, $length, &$children, $class)
    {
        if ($this->tokenIsTerminal) {
            $children = [];
        }
        return new Token($this->tokenGroup, $this->tokenName, $input, $offset, $length, $children, $class);
    }

}