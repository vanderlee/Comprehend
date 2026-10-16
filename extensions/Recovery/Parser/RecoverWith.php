<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Recovery\Parser;

use Closure;
use InvalidArgumentException;
use LogicException;
use Vanderlee\Comprehend\Builder\AnalyzableParser;
use Vanderlee\Comprehend\Builder\DefaultAnalysisTrait;
use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Builder\NullabilityLookup;
use Vanderlee\Comprehend\Builder\ReusableSnapshot;
use Vanderlee\Comprehend\Extensions\Recovery\RecoveryContext;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Result\Diagnostic;
use Vanderlee\Comprehend\Result\Recovery;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\Span;
use Vanderlee\Comprehend\Tree\ConcreteNode;

/** Runs a pure strategy after operand failure and checks its returned source region. */
class RecoverWith extends Parser implements AnalyzableParser, ReusableSnapshot
{
    use DefaultAnalysisTrait;
    use ExactClassSnapshotReuseTrait;

    /** @var Closure(RecoveryContext): ?Span */
    private readonly Closure $strategy;

    /**
     * @param callable(RecoveryContext): ?Span $strategy
     * @param array<string, Parser> $probes Named syntax dependencies, copied with the parser graph.
     */
    public function __construct(
        protected Parser $parser,
        callable $strategy,
        protected array $probes = [],
        private readonly ?int $maxSkippedBytes = null,
    ) {
        if ($maxSkippedBytes !== null && $maxSkippedBytes < 0) {
            throw new InvalidArgumentException('Recovery skip limit cannot be negative');
        }
        $this->probes = self::checkedProbes($probes);
        $this->strategy = static fn(RecoveryContext $context): ?Span => $strategy($context);
    }

    /**
     * @param array<array-key, mixed> $probes
     * @return array<string, Parser>
     */
    private static function checkedProbes(array $probes): array
    {
        $checked = [];
        foreach ($probes as $name => $probe) {
            if (!is_string($name) || $name === '' || !$probe instanceof Parser) {
                throw new InvalidArgumentException('Recovery probes require nonempty string names and Parser values');
            }
            $checked[$name] = $probe;
        }

        return $checked;
    }

    public function __toString(): string
    {
        return 'recover(' . $this->parser . ')';
    }

    public function children(): array
    {
        return [$this->parser, ...array_values($this->probes)];
    }

    protected function copyDependencies(GraphSnapshot $context): void
    {
        parent::copyDependencies($context);
        $this->parser = $context->copy($this->parser);
        foreach ($this->probes as $name => $probe) {
            $this->probes[$name] = $context->copy($probe);
        }
    }

    public function analysisLeadingParsers(NullabilityLookup $lookup): array
    {
        return $this->children();
    }

    public function analysisProgressParsers(): array
    {
        return array_values($this->probes);
    }

    public function analysisNullability(NullabilityLookup $lookup): ?bool
    {
        return $lookup->get($this->parser);
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $attempt = RecoveryContext::attempt($this->parser, $source, $byteOffset, $session);
        if ($attempt instanceof Success) {
            if ($attempt->diagnostic !== null) {
                $this->reportDiagnostic($attempt->diagnostic, $session);
            }
            return $attempt;
        }
        $diagnostic = $attempt->diagnostic ?? new Diagnostic($source, $byteOffset, [(string) $this->parser], []);
        $context = new RecoveryContext(
            $source,
            $byteOffset,
            $diagnostic,
            $session,
            $this->probes,
            $this->maxSkippedBytes,
        );
        try {
            $span = ($this->strategy)($context);
            if ($span !== null) {
                if (
                    $span->source !== $source || $span->start !== $byteOffset
                    || $context->regionTo($span->end) === null
                ) {
                    throw new LogicException('Recovery strategy returned a foreign, empty or over-budget region');
                }

                return $this->recovered($source, $span, $diagnostic);
            }
        } finally {
            $context->close();
        }
        $this->reportDiagnostic($diagnostic, $session);

        return $attempt;
    }

    private function reportDiagnostic(Diagnostic $diagnostic, ParseSession $session): void
    {
        foreach ($diagnostic->expected as $expected) {
            $session->recordFailure($diagnostic->byteOffset, $expected, $diagnostic->rulePaths);
        }
    }

    private function recovered(Source $source, Span $span, Diagnostic $diagnostic): Success
    {
        $recovery = new Recovery($span, $diagnostic);
        $error = new Success($span->byteLength());
        $error->setSourceMetadata($span, null);
        $error->setConcreteNode(new ConcreteNode(static::class, $span, tags: ['error', 'recovered']));
        $error->addRecovery($recovery);
        $error->setValueMapper(static fn(): Recovery => $recovery);
        $match = $this->success($source, $span->start, $span->byteLength(), $error);
        $match->inheritValueFrom($error);

        return $match;
    }
}
