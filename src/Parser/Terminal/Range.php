<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Terminal;

use InvalidArgumentException;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\SourceMode;
use Vanderlee\Comprehend\Source\Utf8Source;

/**
 * Description of RangeParser.
 *
 * @author Martijn
 */
class Range extends Parser
{
    use CaseSensitiveTrait;

    private ?string $first;
    private ?string $last;
    private bool $in;

    public function __construct(?string $first, ?string $last, bool $in = true)
    {
        if ($first === null && $last === null) {
            throw new InvalidArgumentException('Empty arguments');
        }
        $this->first = $first === null ? null : self::parseCharacter($first);
        $this->last = $last === null ? null : self::parseCharacter($last);
        $this->in = $in;
    }

    public function __toString(): string
    {
        $first = $this->first === null ? '*' : json_encode($this->first);
        $last = $this->last === null ? '*' : json_encode($this->last);
        return 'range(' . $first . '..' . $last . ')';
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $this->pushCaseSensitivityToContext($session);
        try {
            $first = $this->first === null ? 0 : $this->sourceOrdinal($this->first, $session);
            $last = $this->last === null ? null : $this->sourceOrdinal($this->last, $session);
            $unit = $session->readSourceUnit($byteOffset);
            if ($unit === null) {
                return $this->failure($source, $byteOffset);
            }
            $value = $this->sourceOrdinal($unit->rawText(), $session);
            $inside = $first <= $value && ($last === null || $value <= $last);
            return $inside === $this->in
                ? $this->success($source, $byteOffset, $unit->byteLength())
                : $this->failure($source, $byteOffset);
        } finally {
            $this->popCaseSensitivityFromContext($session);
        }
    }

    private function sourceOrdinal(string $character, ParseSession $context): int
    {
        $textMode = $context->source()->mode() === SourceMode::UTF8;
        if ($textMode) {
            if ((new Utf8Source($character))->codePointLength() !== 1) {
                throw new InvalidArgumentException('Text Range requires single code-point bounds');
            }
        } elseif (strlen($character) !== 1) {
            throw new InvalidArgumentException('Byte Range requires single-octet bounds');
        }
        $value = $context->handleCase($character);
        if ($textMode && mb_strlen($value, 'UTF-8') !== 1) {
            throw new InvalidArgumentException('Range case mapping must remain one code point');
        }
        return $textMode ? mb_ord($value, 'UTF-8') : ord($value);
    }
}
