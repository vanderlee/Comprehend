<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Compatibility;

use InvalidArgumentException;
use Vanderlee\Comprehend\Core\Token;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\Utf8Source;

/** One explicit coordinate policy for the entire legacy projection. */
final readonly class Coordinates
{
    public function __construct(public Source $source, public CoordinatePolicy $policy)
    {
        if ($policy === CoordinatePolicy::ASCII
            && preg_match('/[\x80-\xFF]/', $source->sliceBytes(0, $source->byteLength())) === 1) {
            throw new InvalidArgumentException('ASCII compatibility requires ASCII input; select an explicit policy');
        }
        if ($policy === CoordinatePolicy::CODE_POINTS && !($source instanceof Utf8Source)) {
            throw new InvalidArgumentException('Code-point coordinates require Utf8Source');
        }
    }

    public function toByteOffset(int $position): int
    {
        if ($this->policy === CoordinatePolicy::CODE_POINTS) {
            return $this->source->byteOffsetForCodePointPosition($position);
        }
        $this->source->span($position, $position);
        return $position;
    }

    public function position(int $byteOffset): int
    {
        if ($this->policy === CoordinatePolicy::CODE_POINTS) {
            return $this->source->codePointPositionForByteOffset($byteOffset);
        }
        $this->source->span($byteOffset, $byteOffset);
        return $byteOffset;
    }

    public function length(int $start, int $end): int
    {
        $this->source->span($start, $end);
        return $this->position($end) - $this->position($start);
    }

    public function token(?Token $token): ?Token
    {
        return $token !== null && $this->policy === CoordinatePolicy::CODE_POINTS
            ? new CoordinateToken($token, $this) : $token;
    }
}
