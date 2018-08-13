<?php

namespace vanderlee\comprehend\directive;

use \vanderlee\comprehend\parser\Parser;
use \vanderlee\comprehend\core\Context;
use \vanderlee\comprehend\core\ArgumentsTrait;

/**
 * Description of OrDirective
 *
 * @author Martijn
 */
class Prefer extends Parser
{

    const FIRST    = 'first';
    const LONGEST  = 'longest';
    const SHORTEST = 'shortest';

    use ArgumentsTrait;

    /**
     * @var \vanderlee\comprehend\parser\Parser;
     */
    private $parser = null;

    /**
     * One of self::*
     * @var integer
     */
    private $preference = null;

    /**
     *
     * @param \vanderlee\comprehend\parser\structure\Choice $parser
     * @param mixed $preference
     * @throws \DomainException
     */
    public function __construct($preference, $parser)
    {
        if (!in_array($preference, [
            self::FIRST,
            self::LONGEST,
            self::SHORTEST])) {
            throw new \DomainException("Invalid preference `{$preference}` ");
        }
        $this->preference = $preference;

        $this->parser = self::getArgument($parser);
    }

    protected function parse(&$input, $offset, Context $context)
    {
        $context->pushPreference($this->preference);
        $match = $this->parser->parse($input, $offset, $context);
        $context->popPreference();
        return $match;
    }

    public function __toString()
    {
        switch ($this->preference) {
            default:
            case self::FIRST:
                return (string) $this->parser;
            case self::LONGEST:
                return 'longest-of' . (string) $this->parser;
            case self::SHORTEST:
                return 'shortest-of' . (string) $this->parser;
        }
    }

}
