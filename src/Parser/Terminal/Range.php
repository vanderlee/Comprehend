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

    private $first = null;
    private $last = null;
    private $in = true;

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

    protected function recognize(Source $input, int $offset, ParseSession $context): AbstractMatch
    {
        $this->pushCaseSensitivityToContext($context);
        try {
            $first = $this->first === null ? 0 : $this->sourceOrdinal($this->first, $context);
            $last = $this->last === null ? null : $this->sourceOrdinal($this->last, $context);
            $unit = $context->readSourceUnit($offset);
            if ($unit === null) {
                return $this->failure($input, $offset);
            }
            $value = $this->sourceOrdinal($unit->rawText(), $context);
            $inside = $first <= $value && ($last === null || $value <= $last);
            return $inside === $this->in
                ? $this->success($input, $offset, $unit->byteLength())
                : $this->failure($input, $offset);
        } finally {
            $this->popCaseSensitivityFromContext($context);
        }
    }

    private function sourceOrdinal($character, ParseSession $context)
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
