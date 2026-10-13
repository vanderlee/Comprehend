<?php

namespace Vanderlee\Comprehend\Builder;

use Exception;
use InvalidArgumentException;
use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Match\Failure;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * Description of Factory.
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
     * @var Definition
     */
    private $definition;
    private $arguments;

    /**
     * @param Definition $definition
     * @param array $arguments
     */
    public function __construct(Definition $definition, array $arguments = [])
    {
        $this->definition = $definition;
        $this->arguments = $arguments;
    }

    /**
     * @param $name
     *
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

        throw new InvalidArgumentException('Property `' . $name . '` does not exist');
    }

    /**
     * @throws Exception
     */
    private function build()
    {
        if ($this->parser === null) {
            $this->parser = $this->definition->generator;
            if (!$this->parser instanceof Parser) {
                if (!is_callable($this->parser)) {
                    throw new Exception('Parser not defined');
                }

                $parser = ($this->parser);
                $this->parser = $parser(...$this->arguments);
            }
        }
    }

    /**
     * Get and validate a set of results for the local scope of this parser.
     *
     * @param AbstractMatch $match
     * @param string $text
     *
     * @return array|false
     */
    private function validateResults(AbstractMatch $match, $text)
    {
        $results = [];
        if ($match instanceof Success) {
            $results = $match->results;
            foreach ($this->definition->validators as $validator) {
                if (!($validator($text, $results))) {
                    return false;
                }
            }
        }

        return $results;
    }

    /**
     * Apply a callback to handle all processors.
     *
     * @param Success $match
     * @param array $localResults
     *
     * @return Success
     */
    private function addProcessors(Success $match, $localResults)
    {
        if (!empty($this->definition->processors)) {
            $processors = $this->definition->processors;
            $match->addResultCallback(function (&$results) use ($processors, $localResults) {
                foreach ($processors as $key => $processor) {
                    $results[$key] = $processor($localResults, $results);
                }
            });
        }

        return $match;
    }

    /**
     * @param string $input
     * @param int $offset
     * @param Context $context
     *
     * @return Failure|Success
     * @throws Exception
     *
     */
    protected function parse(&$input, $offset, Context $context)
    {
        $this->build();

        $match = $this->parser->parseInContext($input, $offset, $context);

        $text = $context->source() !== null
            ? $context->source()->sliceBytes($offset, $offset + $match->length)
            : substr($input, $offset, $match->length);
        $legacy = $context->source() === null || $context->usesLegacySemantics();
        $results = $legacy ? $this->validateResults($match, $text) : [];
        if ($results === false) {
            return $this->failure($input, $offset, $match->length);
        }

        if ($match instanceof Success) {
            $successes = empty($this->definition->processors)
                ? $match
                : [];

            $success = $this->success($input, $offset, $match->length, $successes);
            $success->setConcreteMatches([$match]);
            if (!$legacy) {
                $validators = array_map(fn ($validator) => fn () => $validator($text, $match->results), $this->definition->validators);
                $success->addSemanticValidators($validators);
            }
            if (!empty($this->definition->processors)) {
                // Processed results hide child outputs, but not selected effects.
                $success->addPrecedingMatch($match);
            }

            if ($legacy) { return $this->addProcessors($success, $results); }
            $processors = $this->definition->processors;
            if ($processors !== []) {
                $success->addResultCallback(function (&$output) use ($processors, $match) {
                    $localResults = $match->results;
                    foreach ($processors as $key => $processor) { $output[$key] = $processor($localResults, $output); }
                });
            }
            return $success;
        }

        return $this->failure($input, $offset, $match->length);
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
