<?php

declare(strict_types=1);
/**
 * RFC 5234 - Augmented BNF for Syntax Specifications: ABNF.
 *
 * Updated by RFC 7405
 * Obsoletes RFC 4234
 *
 * @see     https://tools.ietf.org/html/rfc5234
 */

namespace Vanderlee\Comprehend\Library;

/**
 * Exposes the RFC 5234 ABNF rules under their own ruleset name. Reuses the identical grammar
 * inherited from Rfc4234 and supplies core rules to the ABNF compiler.
 */
class Rfc5234 extends Rfc4234
{
    /**
     * @var string
     */
    protected static $name = 'Rfc5234';
}
