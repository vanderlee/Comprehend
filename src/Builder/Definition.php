<?php

declare (strict_types=1);
namespace Vanderlee\Comprehend\Builder;

use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Parser\Parser;
/** Parser or typed factory, with selected-scope validators and lazy processors. */
final class Definition
{
    /** @var Parser|callable(...mixed): Parser */
    public $generator;
    /** @var list<callable(ParseResult): bool|string> */
    public array $validators = [];
    /** @var array<string, callable(array): mixed> */
    public array $processors = [];
    public function __construct(Parser|callable $generator, ?callable $validator = null)
    {
        $this->generator = $generator;
        if ($validator !== null) {
            $this->validators[] = $validator;
        }
    }
    public function setGenerator(Parser|callable $generator): self
    {
        $this->generator = $generator;
        return $this;
    }
    public function clearValidators(): self
    {
        $this->validators = [];
        return $this;
    }
    public function addValidator(callable $validator): self
    {
        $this->validators[] = $validator;
        return $this;
    }
    public function addProcessor(string $name, callable $processor): self
    {
        $this->processors[$name] = $processor;
        return $this;
    }
    public function build(mixed ...$arguments): Implementation
    {
        return new Implementation($this, $arguments);
    }
}
