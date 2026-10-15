<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Directive;

use Vanderlee\Comprehend\Builder\AnalyzableParser;
use Vanderlee\Comprehend\Builder\ForwardingAnalysisTrait;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Parser\Parser;

/** Owns the operand shared by directives; subclasses retain their recognition policies. */
abstract class UnaryDirective extends Parser implements AnalyzableParser
{
    use ForwardingAnalysisTrait;

    protected Parser $parser;

    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
    }

    /** @internal Structural operand for graph analysis. */
    public function getParser(): Parser
    {
        return $this->parser;
    }

    protected function copyDependencies(GraphSnapshot $context): void
    {
        parent::copyDependencies($context);
        $this->parser = $context->copy($this->parser);
    }

    /** @return list<Parser> */
    public function children(): array
    {
        return [$this->parser];
    }

    protected function analysisTarget(): Parser
    {
        return $this->getParser();
    }
}
