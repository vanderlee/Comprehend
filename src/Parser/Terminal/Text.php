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
 * Recognizes a literal sequence at the current source offset. Applies the scoped case policy
 * while preserving matched source bytes and source-unit boundaries.
 *
 * @author Martijn
 */
class Text extends Parser implements AnalyzableParser, ReusableSnapshot
{
    use ExactClassSnapshotReuseTrait;
    use DefaultAnalysisTrait;
    use DiagnosticExpectationTrait;
    use CaseSensitiveTrait;

    private string $text;
    private int $length;
    /** @var list<string>|null */
    private ?array $byteCharacters = null;
    /** @var list<string>|null */
    private ?array $utf8Characters = null;
    private ?int $utf8Length = null;
    /** @var array<string, list<string>> */
    private array $foldedCharacters = [];
    private ?bool $asciiLiteral = null;
    private ?string $asciiFolded = null;

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
            $mode = $source->mode();
            $units = $mode === SourceMode::UTF8
                ? $this->utf8Length ??= (new Utf8Source($this->text))->codePointLength()
                : $this->length;
            if ($session->isCaseSensitive()) {
                return $this->recognizeSensitive($source, $byteOffset, $session, $units);
            }

            $this->asciiLiteral ??= preg_match('/[\x80-\xff]/', $this->text) === 0;
            if ($this->asciiLiteral && $this->length <= $source->byteLength() - $byteOffset) {
                $subject = $source->sliceBytes($byteOffset, $byteOffset + $this->length);
                $this->asciiFolded ??= strtolower($this->text);
                if (preg_match('/[\x80-\xff]/', $subject) === 0 && strtolower($subject) === $this->asciiFolded) {
                    $session->consumeLiteralWork($subject, $units, $byteOffset);

                    return $this->success($source, $byteOffset, $this->length);
                }
            }

            $literal = $this->foldedCharacters[$mode->value] ??= array_map(
                $session->handleCase(...),
                $this->characters($mode),
            );
            $position = $byteOffset;
            foreach ($literal as $character) {
                $unit = $session->readSourceUnit($position);
                if ($unit === null || $session->handleCase($unit->rawText()) !== $character) {
                    return $this->failure($source, $byteOffset, $position - $byteOffset);
                }
                $position = $unit->end;
            }

            return $this->success($source, $byteOffset, $position - $byteOffset);
        } finally {
            $this->popCaseSensitivityFromContext($session);
        }
    }

    private function recognizeSensitive(Source $source, int $offset, ParseSession $session, int $units): AbstractMatch
    {
        $subject = $source->sliceBytes($offset, min($source->byteLength(), $offset + $this->length));
        if ($subject === $this->text) {
            $session->consumeLiteralWork($this->text, $units, $offset);

            return $this->success($source, $offset, $this->length);
        }

        // XOR ends at the shorter operand. Back up an interior differing byte to
        // the beginning of its code point before exposing diagnostic progress.
        $length = $subject === '' || $subject[0] !== $this->text[0]
            ? 0
            : strspn($subject ^ $this->text, "\0");
        while (!$source->isUnitBoundary($offset + $length)) {
            --$length;
        }
        $prefix = substr($this->text, 0, $length);
        $units = $source->mode() === SourceMode::UTF8 ? mb_strlen($prefix, 'UTF-8') : $length;
        $session->consumeLiteralWork($prefix, $units, $offset);
        $session->consumeWork(1, $offset + $length);

        return $this->failure($source, $offset, $length);
    }

    /** @return list<string> */
    private function characters(SourceMode $mode): array
    {
        if ($mode === SourceMode::BYTES) {
            return $this->byteCharacters ??= str_split($this->text);
        }

        if ($this->utf8Characters === null) {
            // Only the case-insensitive fallback needs individual literal units.
            $this->utf8Characters = mb_str_split($this->text, 1, 'UTF-8');
        }

        return $this->utf8Characters;
    }
}
