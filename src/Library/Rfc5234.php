<?php /** @noinspection PhpUndefinedFieldInspection */

/**
 * RFC 5234 - Augmented BNF for Syntax Specifications: ABNF
 *
 * Updated by RFC 7405
 * Obsoletes RFC 4234
 *
 * @see     https://tools.ietf.org/html/rfc5234
 * @package Vanderlee\Comprehend\Library
 */

namespace Vanderlee\Comprehend\Library;

/**
 * BNF is identical to RFC 4234
 */
class Rfc5234 extends Rfc4234
{
    protected static $name = 'Rfc5234';
}