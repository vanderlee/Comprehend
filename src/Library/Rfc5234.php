<?php

declare(strict_types=1);
namespace Vanderlee\Comprehend\Library;

/**
 * Augmented BNF for Syntax Specifications: ABNF.
 *
 * ABNF grammar syntax.
 *
 * Updated by RFC 7405
 * Obsoletes RFC 4234
 *
 * Exposes the RFC 5234 ABNF rules under their own ruleset name. Reuses the identical grammar
 * inherited from Rfc4234 and supplies core rules to the ABNF compiler.
 *
 * @see https://www.rfc-editor.org/rfc/rfc5234
 * @link https://www.rfc-editor.org/rfc/rfc5234.txt
 */
class Rfc5234 extends Rfc4234
{
    /**
     * @var string
     */
    protected static $name = 'Rfc5234';
}
