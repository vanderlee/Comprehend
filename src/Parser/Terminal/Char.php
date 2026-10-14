<?php

declare (strict_types=1);

namespace Vanderlee\Comprehend\Parser\Terminal;

use InvalidArgumentException;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\SourceMode;
use Vanderlee\Comprehend\Source\Utf8Source;

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

    public function __construct(string $character, bool $include = true)
    {
        $this->character = self::parseCharacter($character);
        $this->include = $include;
    }

    public function __toString(): string
    {
        return '\'' . $this->character . '\'';
    }

    protected function recognize(Source $input, int $offset, ParseSession $context): AbstractMatch
    {
        $textMode = $context->source()->mode() === SourceMode::UTF8;
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
}
