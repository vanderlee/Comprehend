<?php

declare(strict_types=1);
/**
 * RFC 4234 - Augmented BNF for Syntax Specifications: ABNF.
 *
 * Obsoleted by RFC 5234
 * Obsoletes RFC 4234
 *
 * @see     https://tools.ietf.org/html/rfc4234
 */

namespace Vanderlee\Comprehend\Library;

/**
 * Exposes the RFC 4234 ABNF rules under their own ruleset name. Reuses Rfc2234 because the
 * implemented grammar is identical.
 */
class Rfc4234 extends Rfc2234
{
    /**
     * @var string
     */
    protected static $name = 'Rfc4234';
}
