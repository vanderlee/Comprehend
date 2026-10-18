<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Library;

use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Library\Internal\Utf8NonAscii;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Tree\ConcreteNode;

/**
 * Internationalized Resource Identifiers (IRIs).
 *
 * Unicode resource identifiers.
 *
 * Extends the URI components with the RFC's ucschar and iprivate ranges. Mapping to
 * URIs, Unicode normalization and IDNA processing are separate operations.
 *
 * @see Rfc3986 URI grammar extended by this RFC.
 * @link https://www.rfc-editor.org/rfc/rfc3987.txt
 */
class Rfc3987 extends Rfc3986
{
    /** @var string */
    protected static $name = 'Rfc3987';

    /** @param array<string, C|Parser|Definition|callable> $overwrites */
    public function __construct(array $overwrites = [])
    {
        $ucschar = (new Utf8NonAscii())->guard(static function (ConcreteNode $node): bool {
            $cp = mb_ord($node->rawText(), 'UTF-8');

            return (($cp >= 0xa0 && $cp <= 0xd7ff)
                || ($cp >= 0xf900 && $cp <= 0xfdcf) || ($cp >= 0xfdf0 && $cp <= 0xffef)
                || ($cp >= 0x10000 && $cp <= 0xdfffd && ($cp & 0xffff) <= 0xfffd)
                || ($cp >= 0xe1000 && $cp <= 0xefffd));
        });
        $private = (new Utf8NonAscii())->guard(static function (ConcreteNode $node): bool {
            $cp = mb_ord($node->rawText(), 'UTF-8');

            return (($cp >= 0xe000 && $cp <= 0xf8ff)
                || ($cp >= 0xf0000 && $cp <= 0xffffd) || ($cp >= 0x100000 && $cp <= 0x10fffd));
        });
        parent::__construct(array_replace([
            'IPv6address' => (new Rfc4291())->rule('ipv6_address'),
            'ucschar' => $ucschar,
            'iprivate' => $private,
            'unreserved' => C::first(C::regex('/[A-Za-z0-9._~-]/'), $this->rule('ucschar')),
            'query' => C::first($this->rule('pchar'), C::text('/'), C::text('?'), $this->rule('iprivate'))->many(),
            'IRI' => $this->rule('URI'),
            'irelative_ref' => $this->rule('relative_ref'),
            self::ROOT => $this->rule('IRI'),
        ], $overwrites));
    }
}
