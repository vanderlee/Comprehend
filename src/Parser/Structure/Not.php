<?php

declare (strict_types=1);
namespace Vanderlee\Comprehend\Parser\Structure;

use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
/**
 * Description of NotParser.
 *
 * @author Martijn
 */
class Not extends Parser
{
    private $parser;
    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
    }
    protected function recognize(Source $input, int $offset, ParseSession $context): AbstractMatch
    {
        $diagnostics = $context->diagnosticState();
        try {
            $match = $this->parser->recognizeInSession($input, $offset, $context);
        } finally {
            $context->restoreDiagnostics($diagnostics);
        }
        return $match instanceof Success ? $this->failure($input, $offset, $match->length) : $this->success($input, $offset);
    }
    public function __toString(): string
    {
        return '!' . $this->parser;
    }
}
