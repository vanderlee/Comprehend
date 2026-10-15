<?php

declare (strict_types=1);

namespace Vanderlee\Comprehend\Builder;

use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Comprehend;
use Vanderlee\Comprehend\Result\ParseResult;

/** Facade, custom parser or factory, with selected-scope validation and processors. */
final class Definition
{
    /** @var Comprehend|Parser|(callable(mixed...): (Comprehend|Parser)) */
    public $generator;
    /** @var list<callable(ParseResult): (bool|string)> */
    public array $validators = [];
    /** @var array<string, callable(array<array-key, mixed>): mixed> */
    public array $processors = [];

    public function __construct(Comprehend|Parser|callable $generator, ?callable $validator = null)
    {
        $this->setGenerator($generator);
        if ($validator !== null) {
            $this->validators[] = $validator;
        }
    }

    public function setGenerator(Comprehend|Parser|callable $generator): self
    {
        $this->generator = $generator instanceof Comprehend ? $generator->parser() : $generator;
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

    public function build(mixed ...$arguments): Comprehend
    {
        return Comprehend::of($this->implementation(...$arguments));
    }

    /** @internal Runtime instance; public authoring uses build(). */
    public function implementation(mixed ...$arguments): Implementation
    {
        return new Implementation($this, $arguments);
    }
}
