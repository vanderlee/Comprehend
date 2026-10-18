<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Terminal;

use InvalidArgumentException;
use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Builder\ReusableSnapshot;
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
 * Recognizes one specified source unit or its complement. Uses the source mode and scoped case
 * policy to compare octets or UTF-8 code points.
 *
 * @author Martijn
 */
class Char extends Parser implements AnalyzableParser, ReusableSnapshot
{
    use ExactClassSnapshotReuseTrait;
    use DefaultAnalysisTrait;
    use DiagnosticExpectationTrait;
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

    public function analysisNullability(NullabilityLookup $lookup): ?bool
    {
        return false;
    }

    public function lexicalLookahead(): ?int
    {
        return strlen($this->character) === 1 && ord($this->character) < 128 ? 1 : null;
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

    public function getCharacter(): string
    {
        return $this->character;
    }

    public function includesCharacter(): bool
    {
        return $this->include;
    }
}
