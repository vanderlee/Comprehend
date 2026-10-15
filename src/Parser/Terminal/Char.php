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
 * Description of CharParser.
 *
 * @author Martijn
 */
class Char extends Parser
{
    use CaseSensitiveTrait;

    private string $character;
    /**
     * Match the specified character (`true`) or everything else (`false`).
     *
     * @var bool
     */
    private bool $include;

    public function __construct(string $character, bool $include = true)
    {
        $this->character = self::parseCharacter($character);
        $this->include = $include;
    }

    public function __toString(): string
    {
        return '\'' . $this->character . '\'';
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $textMode = $session->source()->mode() === SourceMode::UTF8;
        if ($textMode) {
            if ((new Utf8Source($this->character))->codePointLength() !== 1) {
                throw new InvalidArgumentException('Text Char requires one UTF-8 code point');
            }
        } elseif (strlen($this->character) !== 1) {
            throw new InvalidArgumentException('Byte Char requires one octet');
        }
        $unit = $session->readSourceUnit($byteOffset);
        if ($unit === null) {
            return $this->failure($source, $byteOffset);
        }
        $this->pushCaseSensitivityToContext($session);
        try {
            $equal = $session->handleCase($unit->rawText()) === $session->handleCase($this->character);

            return $this->makeMatch($equal === $this->include, $source, $byteOffset, $unit->byteLength());
        } finally {
            $this->popCaseSensitivityFromContext($session);
        }
    }
}
