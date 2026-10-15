<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Builder;

use LogicException;
use Vanderlee\Comprehend\Comprehend;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;
use Throwable;

/**
 * Binds a Definition to factory arguments as an executable Parser. Applies its validators and
 * collection processors through the canonical recognition and semantic lifecycle.
 */
final class Implementation extends Parser implements AnalyzableParser
{
    use ForwardingAnalysisTrait;

    private ?Parser $parser = null;

    /**
     * @param array<array-key, mixed> $arguments
     */
    public function __construct(private Definition $definition, private array $arguments = []) {}

    /** @internal Materialize this parameterized definition before snapshotting. */
    public function graphTarget(): Parser
    {
        return $this->parser();
    }

    private function parser(): Parser
    {
        if ($this->parser !== null) {
            return $this->parser;
        }

        $generator = $this->definition->generator;
        $parser = $generator instanceof Parser || $generator instanceof Comprehend
            ? $generator
            : $generator(...$this->arguments);

        return $this->parser = $this->requireParser($parser);
    }

    private function requireParser(mixed $parser): Parser
    {
        if ($parser instanceof Comprehend) {
            return $parser->parser();
        }
        if (!$parser instanceof Parser) {
            throw new LogicException('Definition factory must return Comprehend or Parser');
        }

        return $parser;
    }

    public function bindSnapshotTarget(Parser $parser): void
    {
        $this->parser = $parser;
    }

    public function snapshot(GraphSnapshot $context): Parser
    {
        $copy = clone $this;
        $context->registerCopy($this, $copy);
        $copy->copyDependencies($context);
        if ($context->resolvesReferences()) {
            $copy->bindSnapshotTarget($context->copy($copy->graphTarget()));
        }

        return $copy;
    }

    protected function copyDependencies(GraphSnapshot $context): void
    {
        parent::copyDependencies($context);
        if ($context->resolvesReferences()) {
            // A freeze regenerates the target from the copied definition and arguments.
            $this->parser = null;
        }
        $this->definition = $context->copyDefinition($this->definition);
        $this->arguments = array_map($context->copyValue(...), $this->arguments);
        if ($this->parser !== null) {
            $this->parser = $context->copy($this->parser);
        }
    }

    /** @return list<Parser> */
    public function children(): array
    {
        return [$this->graphTarget()];
    }

    protected function analysisTarget(): Parser
    {
        return $this->graphTarget();
    }

    public function __toString(): string
    {
        return 'definition';
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $match = $this->parser()->recognizeInSession($source, $byteOffset, $session);
        if (!$match instanceof Success) {
            return $this->failure($source, $byteOffset, $match->length);
        }
        $processors = $this->definition->processors;
        $success = $this->success($source, $byteOffset, $match->length, $processors === [] ? $match : []);
        $success->setConcreteMatches([$match]);
        $success->addSemanticValidators($this->definition->validators);
        if ($processors === []) {
            $success->inheritValueFrom($match);
        } else {
            // Processed collections replace local collections while retaining
            // the concrete tree and selected effect/validation graph.
            $success->addPrecedingMatch($match);
            $this->bindProcessors($success, $match, $processors);
        }

        return $success;
    }

    /** @param array<string, callable(array<array-key, mixed>): mixed> $processors */
    private function bindProcessors(Success $success, Success $match, array $processors): void
    {
        $evaluated = false;
        $values = [];
        $error = null;
        $success->addResultCallback(
            static function (array &$output) use ($processors, $match, &$evaluated, &$values, &$error) {
                if (!$evaluated) {
                    $evaluated = true;
                    try {
                        $local = $match->getResults();
                        foreach ($processors as $name => $processor) {
                            $values[$name] = $processor($local);
                        }
                    } catch (Throwable $failure) {
                        $error = $failure;
                    }
                }
                if ($error !== null) {
                    throw $error;
                }
                foreach ($values as $name => $value) {
                    $output[$name] = $value;
                }
            },
        );
    }
}
