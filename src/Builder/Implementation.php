<?php

namespace Vanderlee\Comprehend\Builder;

use Exception;
use InvalidArgumentException;
use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Match\Failure;
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
    private $parser = null;

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

    /**
     * @param Definition $definition
     * @param array $arguments
     */
    public function __construct(Definition &$definition, array $arguments = [])
    {
        $this->definition = $definition;
        $this->arguments  = $arguments;
    }

    /**
     * @param $name
     * @return callable|null|Parser
     */
    public function __get($name)
    {
        if ($name === 'parser') {
            try {
                $this->build();
            } catch (Exception $exception) {
                $this->parser = null;
            }

            return $this->parser;
        }

        throw new InvalidArgumentException("Property `{$name}` does not exist");
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
                    throw new Exception('Parser not defined');
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
     * @return Failure|Success
     * @throws Exception
     */
    protected function parse(&$input, $offset, Context $context)
    {
        $this->build();

        $match = $this->parser->parse($input, $offset, $context);

        $localResults = []; // this is redundant, but suppresses PHP scanner warnings
        if ($match instanceof Success) {
            $localResults = $match->results;
            foreach ($this->definition->validators as $validator) {
                if (!($validator(substr($input, $offset, $match->length), $localResults))) {
                    return $this->failure($input, $offset, $match->length);
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

        if ($match instanceof Success
            && !empty($this->definition->processors)) {

            foreach ($this->definition->processors as $key => $processor) {
                $match->addResultCallback(function (&$results) use ($key, $processor, $localResults) {
                    $results[$key] = $processor($localResults, $results);
                });
            }
        }

        return $match;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        try {
            $this->build();
        } catch (Exception $e) {
            // ignore
        }

        return (string)$this->parser;
    }

}
