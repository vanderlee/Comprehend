<?php

/** @noinspection PhpUndefinedFieldInspection */

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
 * BNF is identical to RFC 2234.
 */
class Rfc4234 extends Rfc2234
{
    protected static $name = 'Rfc4234';
}
