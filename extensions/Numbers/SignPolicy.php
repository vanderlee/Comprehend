<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Numbers;

/** Controls the spelling of a leading sign independently of numeric bounds. */
enum SignPolicy: string
{
    case MINUS_ONLY = 'minus-only';
    case OPTIONAL = 'optional';
    case REQUIRED = 'required';
    case FORBIDDEN = 'forbidden';

    /** @internal Shared by numeric recognizers; exponent signs have their own grammar. */
    public function pattern(): string
    {
        return match ($this) {
            self::MINUS_ONLY => '-?',
            self::OPTIONAL => '[+-]?',
            self::REQUIRED => '[+-]',
            self::FORBIDDEN => '',
        };
    }
}
