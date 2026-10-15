<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Terminal;

use Exception;
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
 * Recognizes one source unit included in or excluded from a supplied character set. Splits the
 * set according to source mode and applies the scoped case policy.
 *
 * @author Martijn
 */
class Set extends Parser implements AnalyzableParser
{
    use DefaultAnalysisTrait;
    use CaseSensitiveTrait;

    private string $set;
    private string $sourceSet;
    /**
     * Match only characters inside the set (`true`) or outside (`false`).
     *
     * @var bool
     */
    private bool $include;

    /**
     * Match any single character in the set or not in the set.
     *
     * @param string $set
     * @param bool $include Set false to match only characters NOT in the set
     *
     * @throws Exception
     */
    public function __construct(string $set, bool $include = true)
    {
        if (mb_strlen($set) <= 0) {
            throw new InvalidArgumentException('Empty set');
        }
        $this->set = count_chars($set, 3);
        $this->sourceSet = $set;
        $this->include = $include;
    }

    public function analysisNullability(NullabilityLookup $lookup): ?bool
    {
        return false;
    }

    public function __toString(): string
    {
        return ($this->include ? '' : chr(0xac)) . '( \'' . implode('\' | \'', str_split($this->set)) . '\' )';
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $characters = $session->source()->mode() === SourceMode::UTF8 ? mb_str_split(
            (new Utf8Source($this->sourceSet))->sliceBytes(0, strlen($this->sourceSet)),
            1,
            'UTF-8',
        ) : str_split($this->sourceSet);
        $unit = $session->readSourceUnit($byteOffset);
        if ($unit === null) {
            return $this->failure($source, $byteOffset);
        }
        $this->pushCaseSensitivityToContext($session);
        try {
            $value = $session->handleCase($unit->rawText());
            $inside = false;
            foreach ($characters as $character) {
                $inside = $inside || $session->handleCase($character) === $value;
            }

            return $this->makeMatch($inside === $this->include, $source, $byteOffset, $unit->byteLength());
        } finally {
            $this->popCaseSensitivityFromContext($session);
        }
    }
}
