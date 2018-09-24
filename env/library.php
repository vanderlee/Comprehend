<?php

return [
    // ABNF specification
    'abnf'    => \vanderlee\comprehend\library\Rfc2234::class,
    'rfc2234' => \vanderlee\comprehend\library\Rfc2234::class,
    'rfc4234' => \vanderlee\comprehend\library\Rfc2234::class,    // Obsoletes 2234, No syntax changes
    'rfc5234' => \vanderlee\comprehend\library\Rfc2234::class,    // Obsoletes 4234, No syntax changes
    'rfc7405' => \vanderlee\comprehend\library\Rfc7405::class,    // Updates 5234
    // URI
    'uri'     => \vanderlee\comprehend\library\Rfc3986::class,
    'rfc3986' => \vanderlee\comprehend\library\Rfc3986::class,    // Updates 1738, Obsoletes 2732, 2396, 1808
    //'rfc6874' => Rfc3986::class,    // Updates 3986
    //'rfc7320' => Rfc3986::class,    // Updates 3986
    // IPv6
    'ipv6'    => \vanderlee\comprehend\library\Rfc3513::class,
    'rfc3513' => \vanderlee\comprehend\library\Rfc3513::class,
    // SPF
    'spf'     => \vanderlee\comprehend\library\Rfc4408::class,
    'rfc4408' => \vanderlee\comprehend\library\Rfc4408::class,
];