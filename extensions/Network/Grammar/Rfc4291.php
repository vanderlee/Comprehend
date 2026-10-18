<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Network\Grammar;

/**
 * IP Version 6 Addressing Architecture.
 *
 * IPv6 addresses.
 *
 * Obsoletes RFC 3513
 *
 * Provides the IPv6 text forms from Sections 2.2 and 2.3 using the address and prefix
 * rules from Rfc3513. The root recognizes an address with a prefix length;
 * ipv6_address recognizes a bare address.
 *
 * @see Rfc3513 Superseded by this RFC.
 * @see https://www.rfc-editor.org/rfc/rfc4291
 * @link https://www.rfc-editor.org/rfc/rfc4291.txt
 */
class Rfc4291 extends Rfc3513
{
    /** @var string */
    protected static $name = 'Rfc4291';
}
