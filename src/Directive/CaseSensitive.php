<?php

declare (strict_types=1);
namespace Vanderlee\Comprehend\Directive;

use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Parser\Parser;
/**
 * Description of CaseDirective.
 *
 * @author Martijn
 */
class CaseSensitive extends Parser
{
    /**
     * @var Parser
     */
    private $parser;
    /**
     * @var bool
     */
    private $sensitivity;
    /**
     * @param Parser $parser
     * @param bool $sensitivity
     */
    public function __construct(bool $sensitivity, Parser $parser)
    {
        $this->parser = $parser;
        $this->sensitivity = (bool) $sensitivity;
    }
    protected function recognize(Source $input, int $offset, ParseSession $context): AbstractMatch
    {
        $context->pushCaseSensitivity($this->sensitivity);
        try {
            $match = $this->parser->recognizeInSession($input, $offset, $context);
        } finally {
            $context->popCaseSensitivity();
        }
        return $match;
    }
    public function __toString(): string
    {
        return ($this->sensitivity ? 'case' : 'no-case') . '( ' . $this->parser . ' )';
    }
}
