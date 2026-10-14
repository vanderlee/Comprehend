<?php

declare (strict_types=1);
namespace Vanderlee\Comprehend\Directive;

use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Parser\Parser;
/**
 * Description of LexemeDirective.
 *
 * @author Martijn
 */
class Space extends Parser
{
    /**
     * @var null|Parser
     */
    private $spacer;
    /**
     * @var null|Parser
     */
    private $parser;
    /**
     * Set (or disable) a spacer for the parser.
     *
     * @param Parser|null $spacer
     * @param Parser $parser
     */
    public function __construct(?Parser $spacer, Parser $parser)
    {
        $this->spacer = $spacer === null ? null : $spacer;
        $this->parser = $parser;
    }
    protected function recognize(Source $input, int $offset, ParseSession $context): AbstractMatch
    {
        $context->pushSpacer($this->spacer);
        try {
            $match = $this->parser->recognizeInSession($input, $offset, $context);
        } finally {
            $context->popSpacer();
        }
        return $match;
    }
    public function __toString(): string
    {
        return (string) $this->parser;
    }
}
