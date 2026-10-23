<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Optimizer;

use Vanderlee\Comprehend\Match\Failure;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/** @internal First-byte rejection for plain literals not covered by bounded lexical tables. */
final readonly class ChoiceDispatch
{
    private function __construct(private string $firstByte, private string $expectation, private string $rule)
    {
        // Source-free negative recipe; candidates still use their original typed recognizer.
    }

    public static function prepare(Parser $parser): ?self
    {
        $firstByte = self::firstByte($parser);

        return $firstByte === null ? null : new self($firstByte, (string) $parser, $parser->graphLabel());
    }

    /**
     * @param array<array-key, LexicalTable> $lexical
     * @return array<array-key, self>
     */
    public static function alternatives(Choice $choice, array $lexical): array
    {
        $eligible = [];
        $firstBytes = [];
        $firstIndex = null;
        foreach ($choice as $index => $parser) {
            $firstIndex ??= $index;
            $byte = isset($lexical[$index]) ? null : self::firstByte($parser);
            if ($byte !== null) {
                $eligible[$index] = [$parser, $byte];
                $firstBytes[$byte] = true;
            }
        }
        // Do not build recipes when a shared first byte cannot distinguish successes.
        if (count($firstBytes) < 2) {
            return [];
        }
        // Preserve the common early-success path without an extra source read.
        unset($eligible[$firstIndex]);
        $plans = [];
        foreach ($eligible as $index => [$parser, $byte]) {
            $plans[$index] = new self($byte, (string) $parser, $parser->graphLabel());
        }

        return $plans;
    }

    private static function firstByte(Parser $parser): ?string
    {
        if (
            $parser::class !== Text::class || $parser->hasExecutableMetadata()
            || $parser->getCaseSensitivity() === false || $parser->lexicalLookahead() === null
        ) {
            return null;
        }

        return $parser->getText()[0];
    }

    /** Null leaves the alternative on its existing recognition path. */
    public function reject(Source $source, int $offset, ParseSession $session): ?Failure
    {
        if ($session->hasRecognitionLimits() || $session->hasActiveSpacing() || !$session->isCaseSensitive()) {
            return null;
        }
        if ($source->sliceBytes($offset, min($source->byteLength(), $offset + 1)) === $this->firstByte) {
            return null;
        }

        // Match ordinary Text's entry and first mismatching-unit work. Finite budgets
        // retain checked delegation, including depth, recursion and exact exception paths.
        $session->consumeWork(2, $offset);
        $session->recordFailure($offset, $this->expectation, [[...$session->rulePath(), $this->rule]]);
        $failure = new Failure();
        $failure->setSourceMetadata($source->span($offset, $offset), $session->farthestFailure(true));

        return $failure;
    }
}
