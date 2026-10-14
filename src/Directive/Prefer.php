<?php

declare (strict_types=1);
namespace Vanderlee\Comprehend\Directive;

use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Source\Source;
use DomainException;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Parser\Parser;
/**
 * Description of OrDirective.
 *
 * @author Martijn
 */
class Prefer extends Parser
{
    public const FIRST = 'first';
    public const LONGEST = 'longest';
    public const SHORTEST = 'shortest';
    /**
     * @var Parser;
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
     * @throws DomainException
     */
    public function __construct(string $preference, Parser $parser)
    {
        if (!in_array($preference, [self::FIRST, self::LONGEST, self::SHORTEST])) {
            throw new DomainException('Invalid preference `' . $preference . '`');
        }
        $this->preference = $preference;
        $this->parser = $parser;
    }
    protected function recognize(Source $input, int $offset, ParseSession $context): AbstractMatch
    {
        $context->pushPreference($this->preference);
        try {
            $match = $this->parser->recognizeInSession($input, $offset, $context);
        } finally {
            $context->popPreference();
        }
        return $match;
    }
    public function __toString(): string
    {
        switch ($this->preference) {
            default:
            case self::FIRST:
                return (string) $this->parser;
            case self::LONGEST:
                return 'longest-of' . $this->parser;
            case self::SHORTEST:
                return 'shortest-of' . $this->parser;
        }
    }
}
