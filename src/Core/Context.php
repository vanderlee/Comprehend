<?php

namespace Vanderlee\Comprehend\Core;

use InvalidArgumentException;
use LogicException;
use Vanderlee\Comprehend\Core\Context\CaseSensitiveContextTrait;
use Vanderlee\Comprehend\Core\Context\PreferContextTrait;
use Vanderlee\Comprehend\Core\Context\SpacingContextTrait;
use Vanderlee\Comprehend\Directive\Prefer;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\Span;
use Vanderlee\Comprehend\Source\Utf8Source;

/**
 * Maintains the current context of the parser chain.
 *
 * @author Martijn
 */
class Context
{
    use CaseSensitiveContextTrait;
    use PreferContextTrait;
    use SpacingContextTrait;

    private $source;
    private $farthestFailure;
    private $legacyAscii;

    public function __construct(
        $skipper = null,
        $caseSensitive = true,
        $preference = Prefer::FIRST,
        ?Source $source = null,
        bool $legacyAscii = false
    ) {
        if ($source !== null && !($source instanceof ByteSource) && !($source instanceof Utf8Source)) {
            throw new InvalidArgumentException('Recognition currently supports ByteSource and Utf8Source');
        }
        $this->source = $source;
        if ($legacyAscii && ($source === null
            || preg_match('/[\x80-\xFF]/', $source->sliceBytes(0, $source->byteLength())) === 1)) {
            throw new InvalidArgumentException('Legacy subclass compatibility requires an ASCII source');
        }
        $this->legacyAscii = $legacyAscii;
        self::assertPreference($preference);

        $this->pushSpacer($skipper);
        $this->pushCaseSensitivity($caseSensitive);
        $this->pushPreference($preference);
    }

    public function source(): ?Source
    {
        return $this->source;
    }

    public function allowsLegacyAscii(): bool
    {
        return $this->legacyAscii;
    }

    public function recordFailure(int $byteOffset): void
    {
        if ($this->source !== null) {
            $this->source->span($byteOffset, $byteOffset);
            $this->farthestFailure = max($this->farthestFailure ?? $byteOffset, $byteOffset);
        }
    }

    public function farthestFailure(): ?int
    {
        return $this->farthestFailure;
    }

    public function readSourceUnit(int $byteOffset): ?Span
    {
        if ($this->source instanceof Utf8Source) {
            return $this->source->readCodePoint($byteOffset);
        }
        if ($this->source instanceof ByteSource) {
            return $this->source->readByte($byteOffset);
        }
        throw new LogicException('Source recognition requires a source context');
    }
}
