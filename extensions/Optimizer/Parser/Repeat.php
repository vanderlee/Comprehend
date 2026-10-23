<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Optimizer\Parser;

use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Extensions\Optimizer\GraphOptimizer;
use Vanderlee\Comprehend\Extensions\Optimizer\LexicalTable;
use Vanderlee\Comprehend\Extensions\Optimizer\Preparation;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Structure\Repeat as OrdinaryRepeat;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadRun;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;
use Vanderlee\Comprehend\Source\ByteFileSource;
use Vanderlee\Comprehend\Source\Utf8FileSource;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Tree\ConcreteNode;

/** @internal Spacing scopes and the ordinary repetition loop remain in the parent. */
class Repeat extends OrdinaryRepeat
{
    use ExactClassSnapshotReuseTrait;
    use PreparedTriviaTrait;
    use SyntaxSpacingTrait;

    private ?ReadRun $scanner = null;
    private ?LexicalTable $lexical = null;
    private ?string $literalBatch = null;

    public static function from(OrdinaryRepeat $original): self
    {
        $copy = new self($original->getParser(), $original->getMinimum(), $original->getMaximum());
        $original->copyConfigurationTo($copy);

        return $copy;
    }

    public function prepareRecognition(?Preparation $preparation = null): void
    {
        $operand = $this->getParser();
        $this->literalBatch = $operand::class === Text::class && !$operand->hasCompositionMetadata()
            && $operand->getCaseSensitivity() !== false && strlen($operand->getText()) > 1
            && strlen($operand->getText()) <= 8192 && preg_match('/[\x80-\xff]/', $operand->getText()) === 0
            ? str_repeat($operand->getText(), intdiv(8192, strlen($operand->getText()))) : null;
        $this->lexical = $this->scanner === null && $this->getMaximum() !== 0
            ? GraphOptimizer::table($this->getParser(), $preparation) : null;
    }

    protected function copyDependencies(GraphSnapshot $context): void
    {
        parent::copyDependencies($context);
        $this->triviaPrepared = false;
        $this->lexical = null;
        $this->literalBatch = null;
        $this->scanner = $context->optimizationEnabled()
            ? GraphOptimizer::scanner($this->getParser(), $this->getMinimum(), $this->getMaximum()) : null;
    }

    protected function attemptChild(Source $source, int $offset, ParseSession $session): AbstractMatch
    {
        return GraphOptimizer::attempt(
            $this->lexical,
            $this->getParser(),
            $source,
            $offset,
            $session,
            retainFailureDiagnostic: static::class !== self::class,
        );
    }

    protected function recognizeRepetition(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $batch = $this->recognizeLiteralBatch($source, $byteOffset, $session);
        if ($batch !== null) {
            return $batch;
        }
        $scan = GraphOptimizer::scanRepeat($this->scanner, $this->getParser(), $source, $byteOffset, $session);

        if ($scan === null) {
            return parent::recognizeRepetition($source, $byteOffset, $session);
        }
        $success = $this->success($source, $byteOffset, $scan[0]);
        $success->setConcreteNodes($scan[1]);

        return $success;
    }

    private function recognizeLiteralBatch(Source $source, int $offset, ParseSession $session): ?AbstractMatch
    {
        if (
            $this->literalBatch === null || $session->hasRecognitionLimits() || $session->hasActiveSpacing()
            || !$session->isCaseSensitive()
            || !in_array($source::class, [ByteSource::class, Utf8Source::class,
                ByteFileSource::class, Utf8FileSource::class], true)
        ) {
            return null;
        }
        $operand = $this->getParser();
        if (!$operand instanceof Text) {
            return null;
        }
        $width = strlen($operand->getText());
        $maximum = $this->getMaximum();
        $count = 0;
        $end = $offset;
        while ($maximum === null || $count < $maximum) {
            $bytes = $maximum === null ? strlen($this->literalBatch)
                : min(strlen($this->literalBatch), min($maximum - $count, 8192) * $width);
            $subject = $source->sliceBytes($end, $end + min($bytes, $source->byteLength() - $end));
            $matched = intdiv(strspn($subject ^ $this->literalBatch, "\0"), $width);
            $count += $matched;
            $end += $matched * $width;
            if ($matched * $width < $bytes) {
                break;
            }
        }
        if ($count < $this->getMinimum()) {
            // No work or diagnostics have been published: ordinary recognition owns failure progress.
            return null;
        }
        // Account for every original terminal entry and inspected byte. Limits use the scalar path.
        $session->consumeWork($count * ($width + 1), $offset);
        if ($maximum === null || $count < $maximum) {
            $diagnostics = $session->diagnosticState();
            try {
                $operand->recognizeInSession($source, $end, $session, retainFailureDiagnostic: false);
            } finally {
                $session->restoreDiagnostics($diagnostics);
            }
        }
        $children = [];
        for ($position = $offset; $position < $end; $position += $width) {
            $children[] = new ConcreteNode($operand->concreteKind(), $source->span($position, $position + $width));
        }
        $success = $this->success($source, $offset, $end - $offset);
        $success->setConcreteNodes($children);

        return $success;
    }
}
