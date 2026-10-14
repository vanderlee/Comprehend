<?php

declare (strict_types=1);

namespace Vanderlee\Comprehend\Builder;

use LogicException;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/** A parameterized definition instance using the canonical lifecycle. */
final class Implementation extends Parser
{
    private ?Parser $parser = null;

    public function __construct(private readonly Definition $definition, private readonly array $arguments = [])
    {
    }

    /** @internal Materialize this parameterized definition before snapshotting. */
    public function graphTarget(): Parser
    {
        return $this->parser();
    }

    private function parser(): Parser
    {
        if ($this->parser === null) {
            $generator = $this->definition->generator;
            $parser = $generator instanceof Parser ? $generator : $generator(...$this->arguments);
            if (!$parser instanceof Parser) {
                throw new LogicException('Definition factory must return Parser');
            }
            $this->parser = $parser;
        }
        return $this->parser;
    }

    public function bindSnapshotTarget(Parser $parser): void
    {
        $this->parser = $parser;
    }

    public function __toString(): string
    {
        return 'definition';
    }

    protected function recognize(Source $source, int $offset, ParseSession $session): AbstractMatch
    {
        $match = $this->parser()->recognizeInSession($source, $offset, $session);
        if (!$match instanceof Success) {
            return $this->failure($source, $offset, $match->length);
        }
        $processors = $this->definition->processors;
        $success = $this->success($source, $offset, $match->length, $processors === [] ? $match : []);
        $success->setConcreteMatches([$match]);
        $success->addSemanticValidators($this->definition->validators);
        if ($processors === []) {
            $success->inheritValueFrom($match);
        } else {
            // Processed collections replace local collections while retaining
            // the concrete tree and selected effect/validation graph.
            $success->addPrecedingMatch($match);
            $evaluated = false;
            $values = [];
            $error = null;
            $success->addResultCallback(
                static function (array &$output) use ($processors, $match, &$evaluated, &$values, &$error) {
                    if ($error !== null) {
                        throw $error;
                    }
                    if (!$evaluated) {
                        $evaluated = true;
                        try {
                            $local = $match->getResults();
                            foreach ($processors as $name => $processor) {
                                $values[$name] = $processor($local);
                            }
                        } catch (\Throwable $failure) {
                            $error = $failure;
                            throw $failure;
                        }
                    }
                    foreach ($values as $name => $value) {
                        $output[$name] = $value;
                    }
                },
            );
        }
        return $success;
    }
}
