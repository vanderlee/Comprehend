<?php

namespace Vanderlee\Comprehend\Builder;

use Vanderlee\Comprehend\Core\ArgumentsTrait;
use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * Dynamically (lazy) binding parser
 *
 * @author Martijn
 */
class RuleBinding extends Parser
{
    use ArgumentsTrait;

    private $rule;
    private $arguments;

    public function __construct(&$rule, $arguments = [])
    {
        $this->rule      = &$rule;
        $this->arguments = $arguments;
    }

    private function getParser()
    {
        $rule = &$this->rule;

        if ($rule instanceof Parser) {
            return clone $rule;
        }

        if (is_callable($rule)) {
            // Generator function (should return Parser)
            $instance = $rule(...$this->arguments);
            if (!($instance instanceof Parser)) {
                throw new \InvalidArgumentException("Generator function does not return Parser");
            }
            return $instance;
        }

        if (is_string($rule) && class_exists($rule) && is_subclass_of($rule, Parser::class)) {
            // Class name of a Parser class
            return new $rule(...$this->arguments);
        }

        if (is_array($rule) || is_string($rule) || is_int($rule)) {
            // S-C-S Array syntax, plain text or ASCII-code
            return self::getArgument($rule);
        }

        throw new \RuntimeException(sprintf('Cannot dynamically bind definition type `%1$s`',
            is_object($this->rule)
                ? get_class($this->rule)
                : gettype($this->rule)));
    }

    protected function parse(&$input, $offset, Context $context)
    {
        $match = $this->getParser()->parse($input, $offset, $context);
        if ($match->match) {
            return $this->success($input, $offset, $match->length, $match);
        }

        return $this->failure($input, $offset, $match->length);
    }

    public function __toString()
    {
        /** @noinspection HtmlUnknownTag */
        return $this->rule
            ? (string)$this->getParser()
            : '<undefined>';
    }

}
