<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Encodings;

use Vanderlee\Comprehend\Syntax\SpecificationExtension;
use Vanderlee\Comprehend\Extensions\Encodings\Grammar\Rfc4648;

/** Discoverable Encodings entry points over the shared specification grammars. */
final class Encodings extends SpecificationExtension
{
    protected const PARSERS = [
        'base16' => [Rfc4648::class, 'base16'],
        'base32' => [Rfc4648::class, 'base32'],
        'base32Hex' => [Rfc4648::class, 'base32hex'],
        'base64' => [Rfc4648::class, 'base64'],
        'base64Url' => [Rfc4648::class, 'base64url'],
        'base64Unpadded' => [Rfc4648::class, 'base64_unpadded'],
        'base64UrlUnpadded' => [Rfc4648::class, 'base64url_unpadded'],
    ];

    protected const VERSIONS = [
        'rfc4648' => Rfc4648::class,
    ];
}
