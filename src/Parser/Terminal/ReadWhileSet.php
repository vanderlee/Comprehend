<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Terminal;

use InvalidArgumentException;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\SourceMode;
use Vanderlee\Comprehend\Source\Span;
use Vanderlee\Comprehend\Source\Utf8Source;

/** Scans set membership without invoking a child parser or user predicate per unit. */
final class ReadWhileSet extends ReadRun
{
    /** @var array<string, array<string, true>> */
    private array $memberships = [];
    /** @var array<string, true> */
    private array $membership = [];

    public function __construct(private readonly string $characters, int $min = 0, ?int $max = null)
    {
        parent::__construct($min, $max);
        if ($characters === '') {
            throw new InvalidArgumentException('Empty set');
        }
    }

    public function __toString(): string
    {
        return 'readWhileSet(' . bin2hex($this->characters) . ')';
    }

    protected function prepareScan(Source $source, ParseSession $session): void
    {
        $key = $source->mode()->value . ':' . (int) $session->isCaseSensitive();
        if (!isset($this->memberships[$key])) {
            if ($source->mode() === SourceMode::UTF8) {
                new Utf8Source($this->characters);
                $characters = mb_str_split($this->characters, 1, 'UTF-8');
            } else {
                $characters = str_split($this->characters);
            }
            $membership = [];
            foreach ($characters as $character) {
                $membership['unit:' . $session->handleCase($character)] = true;
            }
            $this->memberships[$key] = $membership;
        }
        $this->membership = $this->memberships[$key];
    }

    protected function acceptsUnit(Span $unit, ParseSession $session): bool
    {
        return isset($this->membership['unit:' . $session->handleCase($unit->rawText())]);
    }

    protected function regexUnitPattern(Source $source): string
    {
        $utf8 = $source->mode() === SourceMode::UTF8;
        $characters = $utf8 ? mb_str_split($this->characters, 1, 'UTF-8') : str_split($this->characters);
        $class = '';
        foreach ($characters as $character) {
            // Hex escapes keep punctuation, NUL and arbitrary octets literal inside a class.
            $class .= $utf8 ? '\\x{' . dechex(mb_ord($character, 'UTF-8')) . '}' : '\\x' . bin2hex($character);
        }

        return '[' . $class . ']';
    }
}
