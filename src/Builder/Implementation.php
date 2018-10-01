<?php

namespace Vanderlee\Comprehend\Builder;

use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * Description of Factory
 *
 * @author Martijn
 */
class Implementation extends Parser
{

    /**
     * @var Parser|callable|null
     */
    public $parser = null;

    /**
     * @var callable|null
     */
    public $validator = null;

    /**
     * @var callable|null
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

    public function __get($name)
    {
        if ($name === 'parser') {
            $this->build();
            return $this->parser;
        }
    }

    /**
     * @throws \Exception
     */
    private function build()
    {
        if ($this->parser === null) {
            $this->parser = $this->definition->generator;
            if (!$this->parser instanceof Parser) {
                if (!is_callable($this->parser)) {
                    throw new \Exception('Parser not defined');
                }

                $parser       = ($this->parser);
                $this->parser = $parser(...$this->arguments);
            }
        }
    }

    /**
     * @param string $input
     * @param int $offset
     * @param Context $context
     * @return \Vanderlee\Comprehend\Match\Failure|\Vanderlee\Comprehend\Match\Match|Success
     * @throws \Exception
     */
    protected function parse(&$input, $offset, Context $context)
    {
        $this->build();

        $match = $this->parser->parse($input, $offset, $context);

        $localResults = []; // this is redundant, but suppresses PHP scanner warnings
        if ($match instanceof Success) {
            $localResults = $match->results;

            if (!empty($this->definition->validators)) {
                $text = substr($input, $offset, $match->length);

                foreach ($this->definition->validators as $validator) {
                    if (!($validator($text, $localResults))) {
                        return $this->failure($input, $offset, $match->length);
                    }
                }
            }
        }

        // Copy match into new match, only pass original callbacks if processor not set
        $successes = empty($this->definition->processors)
            ? $match
            : [];
        $match     = ($match instanceof Success)
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
        try {
            $this->build();
        } catch (\Exception $e) {
            // ignore
        }

        return (string)$this->parser;
    }

}
