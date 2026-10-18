<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions;

use Vanderlee\Comprehend\Extensions\Library\SpecificationExtension;
use Vanderlee\Comprehend\Extensions\Library\Rfc1034;
use Vanderlee\Comprehend\Extensions\Library\Rfc1035;
use Vanderlee\Comprehend\Extensions\Library\Rfc1123;
use Vanderlee\Comprehend\Extensions\Library\Rfc3513;
use Vanderlee\Comprehend\Extensions\Library\Rfc3986;
use Vanderlee\Comprehend\Extensions\Library\Rfc3987;
use Vanderlee\Comprehend\Extensions\Library\Rfc4291;
use Vanderlee\Comprehend\Extensions\Library\Rfc6570;
use Vanderlee\Comprehend\Extensions\Library\Rfc8141;

/** Discoverable Network entry points over the shared specification grammars. */
final class Network extends SpecificationExtension
{
    protected const VERSION_ROOTS = ['rfc3986' => 'URI'];

    protected const PARSERS = [
        'ipv4' => [Rfc3986::class, 'IPv4address'],
        'ipv6' => [Rfc4291::class, 'ipv6_address'],
        'hostname' => [Rfc1123::class, 'hostname'],
        'uri' => [Rfc3986::class, 'URI'],
        'uriReference' => [Rfc3986::class, 'URI_reference'],
        'absoluteUri' => [Rfc3986::class, 'absolute_URI'],
        'iri' => [Rfc3987::class, 'IRI'],
        'urn' => [Rfc8141::class, 'urn'],
        'uriTemplate' => [Rfc6570::class, 'uri_template'],
        'hostnameLabel' => [Rfc1123::class, 'label'],
        'uriScheme' => [Rfc3986::class, 'scheme'],
        'uriAuthority' => [Rfc3986::class, 'authority'],
        'uriHost' => [Rfc3986::class, 'host'],
        'uriPort' => [Rfc3986::class, 'port'],
        'uriRelativePart' => [Rfc3986::class, 'relative_part'],
        'uriPathAbempty' => [Rfc3986::class, 'path_abempty'],
        'uriSegment' => [Rfc3986::class, 'segment'],
        'uriQuery' => [Rfc3986::class, 'query'],
        'uriFragment' => [Rfc3986::class, 'fragment'],
        'uriPercentEncoded' => [Rfc3986::class, 'pct_encoded'],
        'uriUnreserved' => [Rfc3986::class, 'unreserved'],
        'iriUcschar' => [Rfc3987::class, 'ucschar'],
        'iriPrivateChar' => [Rfc3987::class, 'iprivate'],
    ];

    protected const VERSIONS = [
        'rfc1034' => Rfc1034::class,
        'rfc1035' => Rfc1035::class,
        'rfc1123' => Rfc1123::class,
        'rfc3513' => Rfc3513::class,
        'rfc3986' => Rfc3986::class,
        'rfc3987' => Rfc3987::class,
        'rfc4291' => Rfc4291::class,
        'rfc6570' => Rfc6570::class,
        'rfc8141' => Rfc8141::class,
    ];
}
