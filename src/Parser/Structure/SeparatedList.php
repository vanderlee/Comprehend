<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Structure;

use InvalidArgumentException;
use LogicException;
use Vanderlee\Comprehend\Builder\AnalyzableParser;
use Vanderlee\Comprehend\Builder\DefaultAnalysisTrait;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Builder\NullabilityLookup;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/** Recognizes separated, consuming items; keeps syntax while lazily projecting item values. */
class SeparatedList extends Parser implements AnalyzableParser
{
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

    /** @return list<Parser> */
    public function children(): array
    {
        $children = [$this->item, $this->separator];
        if ($this->spacer !== null) {
            $children[] = $this->spacer;
        }

        return $children;
    }

    protected function copyDependencies(GraphSnapshot $context): void
    {
        parent::copyDependencies($context);
        $this->item = $context->copy($this->item);
        $this->separator = $context->copy($this->separator);
        if ($this->spacer !== null) {
            $this->spacer = $context->copy($this->spacer);
        }
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
                    $separatorDiagnostics = $session->diagnosticState();
                    $separator = $this->attempt(
                        $this->separator,
                        $source,
                        $cursor,
                        $session,
                        $items !== [],
                        $pairSelected,
                        $pairConcrete,
                    );
                    if ($separator instanceof Success) {
                        $hasSeparator = true;
                    } elseif ($items !== []) {
                        if (count($items) >= $this->min) {
                            $session->restoreDiagnostics($diagnostics);
                        }
                        break;
                    } else {
                        // An absent optional leading separator must not affect item diagnostics.
                        $session->restoreDiagnostics($separatorDiagnostics);
                        $cursor = $end;
                    }
                }

                $afterSeparator = $cursor;
                $separatorSelected = $pairSelected;
                $separatorConcrete = $pairConcrete;
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
                        $session->restoreDiagnostics($diagnostics);
                        // A leading separator alone never constitutes a list.
                        if ($items !== [] && $this->allowTrailing) {
                            array_push($selected, ...$separatorSelected);
                            array_push($concrete, ...$separatorConcrete);
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
                $tailSelected = [];
                $tailConcrete = [];
                $tail = $this->attempt(
                    $this->separator,
                    $source,
                    $cursor,
                    $session,
                    true,
                    $tailSelected,
                    $tailConcrete,
                );
                if ($tail instanceof Success) {
                    array_push($selected, ...$tailSelected);
                    array_push($concrete, ...$tailConcrete);
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
        $success->setConcreteMatches($concrete);
        $success->setValueMapper(
            static fn(): array => array_map(static fn(Success $item): mixed => $item->getValue(), $items),
        );

        return $success;
    }

    /**
     * Stage syntax and trivia until the enclosing pair is accepted.
     * @param list<Success> $selected
     * @param list<Success> $concrete
     */
    private function attempt(
        Parser $parser,
        Source $source,
        int &$cursor,
        ParseSession $session,
        bool $withSpacing,
        array &$selected,
        array &$concrete,
    ): ?Success {
        $spacing = null;
        $skip = $withSpacing ? $session->skipSpacing($source, $cursor, $spacing) : 0;
        if ($skip === false) {
            return null;
        }
        $match = $parser->recognizeInSession($source, $cursor + $skip, $session);
        if (!$match instanceof Success) {
            return null;
        }
        if ($spacing !== null) {
            $match->addPrecedingMatch($spacing);
            $concrete[] = $spacing;
        }
        $selected[] = $match;
        $concrete[] = $match;
        $cursor += $skip + $match->length;

        return $match;
    }
}
