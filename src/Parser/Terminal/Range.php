<?php

namespace Vanderlee\Comprehend\Parser\Terminal;

use Vanderlee\Comprehend\Source\Utf8Source;

use InvalidArgumentException;
use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Parser\Parser;

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

    public function __construct($first, $last, $in = true)
    {
        if ($first === null
            && $last === null) {
            throw new InvalidArgumentException('Empty arguments');
        }

        $this->first = $first === null
            ? null
            : self::parseCharacter($first);

        $this->last = $last === null
            ? null
            : self::parseCharacter($last);

        $this->in = (bool)$in;
    }

    protected function parse(&$input, $offset, Context $context)
    {
        if ($context->source() !== null) {
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
        if ($offset >= mb_strlen($input)) {
            return $this->failure($input, $offset);
        }

        $isSuccess = !$this->in; // By default not successful if required to match only within range

        $this->pushCaseSensitivityToContext($context);

        try {
            $first = ord($context->handleCase($this->first));
            $last = ord($context->handleCase($this->last));
            $ord = ord($context->handleCase($input[$offset]));
        } finally {
            $this->popCaseSensitivityFromContext($context);
        }

        if ($first <= $ord
            && ($this->last === null
                || $ord <= $last)) {
            $isSuccess = !$isSuccess;
        }

        return $isSuccess
            ? $this->success($input, $offset, 1)
            : $this->failure($input, $offset);
    }

    public function __toString()
    {
        return sprintf('x%02x-x%02x', ord($this->first), ord($this->last));
    }

    private function sourceOrdinal($character, Context $context)
    {
        $textMode = $context->source() instanceof Utf8Source;
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
