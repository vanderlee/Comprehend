<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Terminal;

use InvalidArgumentException;
use Vanderlee\Comprehend\Builder\AnalyzableParser;
use Vanderlee\Comprehend\Builder\DefaultAnalysisTrait;
use Vanderlee\Comprehend\Builder\NullabilityLookup;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\SourceMode;
use Vanderlee\Comprehend\Source\Utf8Source;

/**
 * Recognizes a literal sequence at the current source offset. Applies the scoped case policy
 * while preserving matched source bytes and source-unit boundaries.
 *
 * @author Martijn
 */
class Text extends Parser implements AnalyzableParser
{
    use DefaultAnalysisTrait;
    use CaseSensitiveTrait;

    private string $text;
    private int $length;

    public function __construct(string $text)
    {
        $this->text = $text;
        $this->length = strlen($text);
        if ($this->length <= 0) {
            throw new InvalidArgumentException('Empty argument');
        }
    }

    public function analysisNullability(NullabilityLookup $lookup): ?bool
    {
        return false;
    }

    public function __toString(): string
    {
        return '"' . $this->text . '"';
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $this->pushCaseSensitivityToContext($session);
        try {
            $literal = $session->source()->mode() === SourceMode::UTF8 ? mb_str_split(
                (new Utf8Source($this->text))->sliceBytes(0, strlen($this->text)),
                1,
                'UTF-8',
            ) : str_split($this->text);
            $position = $byteOffset;
            foreach ($literal as $character) {
                $unit = $session->readSourceUnit($position);
                if ($unit === null || $session->handleCase($unit->rawText()) !== $session->handleCase($character)) {
                    return $this->failure($source, $byteOffset, $position - $byteOffset);
                }
                $position = $unit->end;
            }

            return $this->success($source, $byteOffset, $position - $byteOffset);
        } finally {
            $this->popCaseSensitivityFromContext($session);
        }
    }
}
