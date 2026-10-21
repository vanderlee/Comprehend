<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Optimizer\Parser;

use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\SeparatedList as OrdinarySeparatedList;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Tree\ConcreteNode;

/** @internal The ordinary list loop owns bounds, rollback, trivia and lazy item values. */
class SeparatedList extends OrdinarySeparatedList
{
    use ExactClassSnapshotReuseTrait;

    private ?Text $literal = null;

    public static function from(OrdinarySeparatedList $original): self
    {
        $children = $original->children();
        $copy = new self($children[0], $children[1]);
        $original->copyConfigurationTo($copy);

        return $copy;
    }

    public function prepareRecognition(): void
    {
        $separator = $this->children()[1];
        $this->literal = $separator::class === Text::class && !$separator->hasCompositionMetadata()
            && preg_match('/[\x80-\xff]/', $separator->getText()) === 0 ? $separator : null;
    }

    protected function copyDependencies(GraphSnapshot $context): void
    {
        parent::copyDependencies($context);
        $this->literal = null;
    }

    protected function recognizeSeparator(
        Parser $parser,
        Source $source,
        int $offset,
        ParseSession $session,
        ?Success $spacing,
    ): AbstractMatch|ConcreteNode {
        if (
            $this->literal === null || $session->hasRecognitionLimits()
            || ($spacing !== null && $spacing->hasDeferredState())
            || !($this->literal->getCaseSensitivity() ?? $session->isCaseSensitive())
        ) {
            return parent::recognizeSeparator($parser, $source, $offset, $session, $spacing);
        }
        $text = $this->literal->getText();
        $end = $offset + strlen($text);
        if ($end > $source->byteLength() || $source->sliceBytes($offset, $end) !== $text) {
            // Spacing has already run; only the literal retries for its original failure diagnostics.
            return parent::recognizeSeparator($parser, $source, $offset, $session, $spacing);
        }
        $session->consumeLiteralWork($text, strlen($text), $offset);

        return new ConcreteNode($parser->concreteKind(), $source->span($offset, $end));
    }
}
