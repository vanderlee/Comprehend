<?php /** @noinspection PhpUndefinedFieldInspection */

/**
 * RFC 3513 - Internet Protocol Version 6 (IPv6) Addressing Architecture
 *
 * Obsoleted by RFC 4291
 * Obsoletes RFC 2373
 *
 * @see https://tools.ietf.org/html/rfc4408
 * @package vanderlee\comprehend\library
 */

namespace vanderlee\comprehend\library;

use vanderlee\comprehend\builder\AbstractRuleset;
use vanderlee\comprehend\parser\Parser;
use vanderlee\comprehend\parser\terminal\Integer;

require_once 'functions.php';

/**
 * IPv6 address
 * Supports formats:
 *  1234:0:0:0:0:0:200C:417A
 *  1234::200C:417A,
 *  1234:0:0:0:0:0:12.34.56.78
 *  1234::12.34.56.78
 *
 * @property-read Parser ipv6 IPv6 address with CIDR range
 * @property-read Parser ipv6_address IPv6 address without CIDR range
 *
 * @package vanderlee\comprehend\library
 */
class Rfc3513 extends AbstractRuleset
{
    protected static $name = 'Rfc4408';

    public function __construct($overwrites = [])
    {
        /*
         * We'll use RFC3986 for the IPv6 definition for convenience sake.
         * This may not be 100% accurate, but RFC 3513's definition is sloppy at best.
         */
        $rfc3986 = new Rfc3986;

        /*
         * Normal rules
         */
        $rules = [
            // 2.3 Text Representation of Address Prefixes
            'ipv6'          => [$this->ipv6_address, '/', $this->prefix_length],
            'ipv6_address'  => $rfc3986->IPv6address,
            'prefix_length' => new Integer(0, 128),

            self::DEFAULT => $this->ipv6,
        ];

        parent::__construct(array_merge($rules, $overwrites));
    }
}