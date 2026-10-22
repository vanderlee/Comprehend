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
            // Construction already checked unit count; byte literals may still be invalid UTF-8.
            if (!mb_check_encoding($this->character, 'UTF-8')) {
                throw new InvalidArgumentException('Source must contain valid UTF-8');
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

            if ($this::class !== self::class) {
                return $this->makeMatch($equal === $this->include, $source, $byteOffset, $unit->byteLength());
            }

            return $equal === $this->include
                ? $this->successFromSpan($unit)
                : $this->failure($source, $byteOffset);
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
