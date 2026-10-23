<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Structure;

use InvalidArgumentException;
use LogicException;
use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Builder\ReusableSnapshot;
use Vanderlee\Comprehend\Builder\AnalyzableParser;
use Vanderlee\Comprehend\Builder\DefaultAnalysisTrait;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Builder\NullabilityLookup;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Tree\ConcreteNode;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/** Recognizes separated, consuming items; retains concrete syntax and a raw-text default value. */
class SeparatedList extends Parser implements AnalyzableParser, ReusableSnapshot
{
    use ExactClassSnapshotReuseTrait;
    use DefaultAnalysisTrait;
    use SpacingTrait;

    public function __construct(
        private Parser $item,
        private Parser $separator,
        private int $min = 0,
        private ?int $max = null,
        private bool $allowLeading = false,
        private bool $allowTrailing = false,
    ) {
        if ($min < 0 || ($max !== null && $max < $min)) {
            throw new InvalidArgumentException('Invalid separated list range specified');
        }
    }

    public function __toString(): string
    {
        return 'separated(' . $this->item . ', ' . $this->separator . ')';
    }

    protected function copyConfigurationTo(Parser $copy): void
    {
        if (!$copy instanceof self) {
            throw new InvalidArgumentException('Separated list configuration requires a SeparatedList destination');
        }
        parent::copyConfigurationTo($copy);
        $copy->item = $this->item;
        $copy->separator = $this->separator;
        $copy->min = $this->min;
        $copy->max = $this->max;
        $copy->allowLeading = $this->allowLeading;
        $copy->allowTrailing = $this->allowTrailing;
        $copy->spacer = $this->spacer;
        $copy->hasSpacingPolicy = $this->hasSpacingPolicy;
    }

    /** @return list<Parser> */
    public function children(): array
    {
        return $this->childrenWithSpacer([$this->item, $this->separator]);
    }

    protected function copyDependencies(GraphSnapshot $context): void
    {
        parent::copyDependencies($context);
        $this->item = $context->copy($this->item);
        $this->separator = $context->copy($this->separator);
        $this->copySpacer($context);
    }

    /** @return list<Parser> */
    public function analysisLeadingParsers(NullabilityLookup $lookup): array
    {
        if ($this->max === 0) {
            return [];
        }

        return $this->allowLeading ? [$this->separator, $this->item] : [$this->item];
    }

    /** @return list<Parser> */
    public function analysisProgressParsers(): array
    {
        return $this->max === 0 ? [] : [$this->item];
    }

    public function analysisNullability(NullabilityLookup $lookup): ?bool
    {
        return $this->min === 0 ? true : $lookup->get($this->item);
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $items = [];
        $selected = [];
        $concrete = [];
        $end = $byteOffset;
        $this->pushSpacer($session);
        try {
            while ($this->max === null || count($items) < $this->max) {
                $diagnostics = $session->diagnosticState();
                $cursor = $end;
                $pairSelected = [];
                $pairConcrete = [];
                $hasSeparator = false;
                if ($items !== [] || $this->allowLeading) {
                    $separator = $this->attempt(
                        $this->separator,
                        $source,
                        $cursor,
                        $session,
                        $items !== [],
                        $pairSelected,
                        $pairConcrete,
                        true,
                    );
                    if ($separator !== null) {
                        $hasSeparator = true;
                    } elseif ($items !== []) {
                        if (count($items) >= $this->min) {
                            $session->deferListDiagnostics($diagnostics);
                        }
                        break;
                    } else {
                        // An absent optional leading separator must not affect item diagnostics.
                        $session->restoreDiagnostics($diagnostics);
                        $cursor = $end;
                    }
                }

                $afterSeparator = $cursor;
                // attempt() appends only on success, so a failed item leaves the
                // separator buffers intact without copy-on-write snapshots.
                $item = $this->attempt(
                    $this->item,
                    $source,
                    $cursor,
                    $session,
                    $hasSeparator,
                    $pairSelected,
                    $pairConcrete,
                );
                if (!$item instanceof Success) {
                    if (count($items) >= $this->min) {
                        $session->deferListDiagnostics($diagnostics);
                        // A leading separator alone never constitutes a list.
                        if ($items !== [] && $this->allowTrailing) {
                            array_push($selected, ...$pairSelected);
                            array_push($concrete, ...$pairConcrete);
                            $end = $afterSeparator;
                        }
                    }
                    break;
                }
                if ($item->length === 0) {
                    throw new LogicException('Separated list item matched successfully without consuming input');
                }
                $items[] = $item;
                array_push($selected, ...$pairSelected);
                array_push($concrete, ...$pairConcrete);
                $end = $cursor;
            }

            // At the bound, only an explicitly permitted trailing separator may extend the list.
            if ($items !== [] && $this->max !== null && count($items) === $this->max && $this->allowTrailing) {
                $diagnostics = $session->diagnosticState();
                $cursor = $end;
                $tail = $this->attempt(
                    $this->separator,
                    $source,
                    $cursor,
                    $session,
                    true,
                    $selected,
                    $concrete,
                    true,
                );
                if ($tail !== null) {
                    $end = $cursor;
                } else {
                    $session->restoreDiagnostics($diagnostics);
                }
            }
        } finally {
            $this->popSpacer($session);
        }
        if (count($items) < $this->min) {
            return $this->failure($source, $byteOffset, $end - $byteOffset);
        }
        $success = $this->success($source, $byteOffset, $end - $byteOffset, $selected);
        $success->setConcreteChildren($concrete);
        $this->prepareListValue($success, $items, $selected);

        return $success;
    }

    /**
     * Specializations may project selected syntax without changing recognition or lifecycle.
     * @param list<Success> $items
     * @param list<Success> $selected Items and separators, excluding automatic trivia.
     */
    protected function prepareListValue(Success $success, array $items, array $selected): void
    {
        $success->setValueMapper(
            static fn(): array => array_map(static fn(Success $item): mixed => $item->getValue(), $items),
        );
    }

    /**
     * Append syntax and trivia only after this operand succeeds. A failed
     * operand leaves both buffers unchanged, including its preceding spacing.
     * @param list<Success> $selected
     * @param list<Success|ConcreteNode> $concrete
     */
    private function attempt(
        Parser $parser,
        Source $source,
        int &$cursor,
        ParseSession $session,
        bool $withSpacing,
        array &$selected,
        array &$concrete,
        bool $isSeparator = false,
    ): Success|ConcreteNode|null {
        $spacing = null;
        $skip = $withSpacing ? $this->skipSpacing($source, $cursor, $session, $spacing) : 0;
        if ($skip === false) {
            return null;
        }
        $match = $isSeparator
            ? $this->recognizeSeparator(
                $parser,
                $source,
                $cursor + $skip,
                $session,
                $spacing instanceof Success ? $spacing : null,
            )
            : $parser->recognizeInSession(
                $source,
                $cursor + $skip,
                $session,
                retainFailureDiagnostic: static::class !== self::class,
            );
        if ($match instanceof ConcreteNode) {
            // Direct separators are stateless, including their preceding automatic trivia.
            if ($spacing !== null) {
                $concrete[] = $spacing;
            }
            $concrete[] = $match;
            $cursor = $match->span->end;

            return $match;
        }
        if (!$match instanceof Success) {
            return null;
        }
        if ($spacing !== null) {
            if ($spacing instanceof Success) {
                $match->addPrecedingMatch($spacing);
            }
            $concrete[] = $spacing;
        }
        $selected[] = $match;
        $concrete[] = $match;
        $cursor += $skip + $match->length;

        return $match;
    }

    /** @internal Specializations may construct plain separator syntax without a temporary execution record. */
    protected function recognizeSeparator(
        Parser $parser,
        Source $source,
        int $offset,
        ParseSession $session,
        ?Success $spacing,
    ): AbstractMatch|ConcreteNode {
        return $parser->recognizeInSession(
            $source,
            $offset,
            $session,
            retainFailureDiagnostic: static::class !== self::class,
        );
    }
}
