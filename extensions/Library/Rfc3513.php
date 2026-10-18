<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Library;

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
 * @see Rfc4291 Supersedes this RFC.
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
        $rfc3986 = new Rfc3986();
        $forms = [
            $this->hexGroups(8),
            C::sequence($this->hexGroups(6), C::text(':'), $this->rule('ipv4_address')),
        ];
        // Enumerate exact group counts around :: so greedy repeats cannot consume
        // its first colon. Compression must replace at least one 16-bit group.
        for ($left = 0; $left <= 7; $left++) {
            for ($right = 0; $right <= 7 - $left; $right++) {
                $forms[] = C::sequence($this->hexGroups($left), C::text('::'), $this->hexGroups($right));
                if ($left + $right <= 5) {
                    $forms[] = C::sequence(
                        $this->hexGroups($left),
                        C::text('::'),
                        $right === 0 ? C::nothing() : C::sequence($this->hexGroups($right), C::text(':')),
                        $this->rule('ipv4_address'),
                    );
                }
            }
        }
        $rules = [
            // 2.3 Text Representation of Address Prefixes
            'ipv6' => C::sequence(
                $this->rule('ipv6_address'),
                C::text('/'),
                $this->rule('prefix_length'),
            ),
            'h16' => $rfc3986->rule('h16')->caseSensitive(false),
            'ipv4_address' => $rfc3986->rule('IPv4address'),
            'ipv6_address' => C::longest(...$forms),
            'prefix_length' => C::integer(0, 128),
            self::ROOT => $this->rule('ipv6'),
        ];
        parent::__construct(array_merge($rules, $overwrites));
    }

    private function hexGroups(int $count): C
    {
        if ($count === 0) {
            return C::nothing();
        }

        return C::sequence(
            $this->rule('h16'),
            C::sequence(C::text(':'), $this->rule('h16'))->repeat($count - 1, $count - 1),
        );
    }
}
