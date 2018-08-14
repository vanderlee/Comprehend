<?php

namespace vanderlee\comprehend\builder;

use \vanderlee\comprehend\parser\Parser;
use \vanderlee\comprehend\core\Context;
use \vanderlee\comprehend\match\Success;

/**
 * Description of Factory
 *
 * @author Martijn
 */
class DefinitionInstance extends Parser
{

    /**
     * @var Parser
     */
    public $parser = null;

    /**
     * @var callable
     */
    public $validator = null;

    /**
     * @var callable
     */
    public $processor    = null;
    public $processorKey = null;

    /**
     * @var Definition
     */
    private $definition = null;
    private $arguments  = null;

    public function __construct(Definition &$definition, array $arguments = [])
    {
        $this->definition = $definition;
        $this->arguments  = $arguments;
    }

    private function build()
    {
        if ($this->parser === null) {
            $this->parser = $this->definition->generator;
            if (!$this->parser instanceof Parser) {
                if (is_callable($this->parser)) {
                    $this->parser = ($this->parser)(...$this->arguments);
                } else {
                    throw new \Exception('Parser not defined');
                }
            }
        }
    }

    protected function parse(&$input, $offset, Context $context)
    {
        $this->build();

        $match = $this->parser->parse($input, $offset, $context);

        $localResults = []; // this is redundant, but suppresses PHP scanner warnings
        if ($match instanceof Success) {
            $localResults = $match->getResults();

            if (!empty($this->definition->validators)) {
                $text = substr($input, $offset, $match->length);

                foreach ($this->definition->validators as $validator) {
                    if (!($validator)($text, $localResults)) {
                        return $this->failure($input, $offset, $match->length);
                    }
                }
            }
        }

        // Copy match into new match, only pass original callbacks if processor not set
        $successes = empty($this->definition->processors) ? $match : [];
        $match = $match->match
            ? $this->success($input, $offset, $match->length, $successes)
            : $this->failure($input, $offset, $match->length);

        if ($match instanceof Success && !empty($this->definition->processors)) {
            foreach ($this->definition->processors as $key => $processor) {
                $match->addResultCallback(function (&$results) use ($key, $processor, $localResults) {
                    $results[$key] = $processor($localResults, $results);
                });
            }
        }

        return $match;
    }

    public function __toString()
    {
        $this->build();

        return (string)$this->parser;
    }

}
