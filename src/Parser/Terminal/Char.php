<?php

namespace Vanderlee\Comprehend\Parser\Terminal;

use InvalidArgumentException;
use Vanderlee\Comprehend\Source\Utf8Source;

use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * Description of CharParser.
 *
 * @author Martijn
 */
class Char extends Parser
{
    use CaseSensitiveTrait;

    private $character;

    /**
     * Match the specified character (`true`) or everything else (`false`).
     *
     * @var bool
     */
    private $include;

    public function __construct($character, $include = true)
    {
        $this->character = self::parseCharacter($character);
        $this->include = $include;
    }

    protected function parse(&$input, $offset, Context $context)
    {
        if ($context->source() !== null) {
            $textMode = $context->source() instanceof Utf8Source;
            if ($textMode) {
                if ((new Utf8Source($this->character))->codePointLength() !== 1) {
                    throw new InvalidArgumentException('Text Char requires one UTF-8 code point');
                }
            } elseif (strlen($this->character) !== 1) {
                throw new InvalidArgumentException('Byte Char requires one octet');
            }
            $unit = $context->readSourceUnit($offset);
            if ($unit === null) {
                return $this->failure($input, $offset);
            }
            $this->pushCaseSensitivityToContext($context);
            try {
                $equal = $context->handleCase($unit->rawText()) === $context->handleCase($this->character);
                return $this->makeMatch($equal === (bool)$this->include, $input, $offset, $unit->byteLength());
            } finally {
                $this->popCaseSensitivityFromContext($context);
            }
        }
        if ($offset >= mb_strlen($input)) {
            return $this->failure($input, $offset);
        }

        $this->pushCaseSensitivityToContext($context);

        try {
            if ($context->handleCase($input[$offset]) === $context->handleCase($this->character)) {
                return $this->makeMatch($this->include, $input, $offset, 1);
            }
        } finally {
            $this->popCaseSensitivityFromContext($context);
        }

        return $this->makeMatch(!$this->include, $input, $offset, 1);
    }

    public function __toString()
    {
        return '\'' . $this->character . '\'';
    }
}
