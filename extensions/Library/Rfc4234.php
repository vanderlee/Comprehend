<?php

declare(strict_types=1);
namespace Vanderlee\Comprehend\Extensions\Library;

/**
 * Augmented BNF for Syntax Specifications: ABNF.
 *
 * ABNF grammar syntax.
 *
 * Obsoleted by RFC 5234
 * Obsoletes RFC 4234
 *
 * Exposes the RFC 4234 ABNF rules under their own ruleset name. Reuses Rfc2234 because the
 * implemented grammar is identical.
 *
 * @see Rfc5234 Supersedes this RFC.
 * @see https://www.rfc-editor.org/rfc/rfc4234
 * @link https://www.rfc-editor.org/rfc/rfc4234.txt
 */
class Rfc4234 extends Rfc2234
{
    /**
     * @var string
     */
    protected static $name = 'Rfc4234';
}
