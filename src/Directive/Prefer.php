<?php

namespace Vanderlee\Comprehend\Directive;

use Vanderlee\Comprehend\Core\ArgumentsTrait;
use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * Description of OrDirective.
 *
 * @author Martijn
 */
class Prefer extends Parser
{
    const FIRST = 'first';
    const LONGEST = 'longest';
    const SHORTEST = 'shortest';

    use ArgumentsTrait;

    /**
     * @var \Vanderlee\Comprehend\Parser\Parser;
     */
    private $parser = null;

    /**
     * One of self::*.
     *
     * @var int
     */
    private $preference = null;

    /**
     * @param mixed $preference
     * @param mixed $parser
     *
     * @throws \DomainException
     */
    public function __construct($preference, $parser)
    {
        if (!in_array($preference, [
            self::FIRST,
            self::LONGEST,
            self::SHORTEST,
        ])) {
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
                return 'longest-of'.(string) $this->parser;
            case self::SHORTEST:
                return 'shortest-of'.(string) $this->parser;
        }
    }
}
