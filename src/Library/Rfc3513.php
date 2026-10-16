<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Library;

use Vanderlee\Comprehend\Builder\AbstractRuleset;
use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * Internet Protocol Version 6 (IPv6) Addressing Architecture.
 *
 * IPv6 addresses.
 *
 * Obsoleted by RFC 4291
 * Obsoletes RFC 2373
 *
 * Provides reusable IPv6 address rules, including compressed and embedded IPv4 forms. Composes
 * named parser rules so address syntax can be embedded in larger grammars.
 *
 * @see https://www.rfc-editor.org/rfc/rfc3513
 * @link https://www.rfc-editor.org/rfc/rfc3513.txt
 */
class Rfc3513 extends AbstractRuleset
{
    /**
     * @var string
     */
    protected static $name = 'Rfc3513';

    /**
     * @param array<string, C|Parser|Definition|callable> $overwrites
     */
    public function __construct($overwrites = [])
    {
        /*
         * We'll use RFC3986 for the IPv6 definition for convenience' sake.
         * This may not be 100% accurate, but RFC 3513's definition is sloppy at best.
         */
        $rfc3986 = new Rfc3986();
        /*
         * Normal rules
         */
        $rules = [
            // 2.3 Text Representation of Address Prefixes
            'ipv6' => C::sequence(
                $this->rule('ipv6_address'),
                C::text('/'),
                $this->rule('prefix_length'),
            ),
            'ipv6_address' => $rfc3986->rule('IPv6address'),
            'prefix_length' => C::integer(0, 128),
            self::ROOT => $this->rule('ipv6'),
        ];
        parent::__construct(array_merge($rules, $overwrites));
    }
}
