<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Vanderlee\Comprehend\Extensions\Network\Grammar\Rfc4291;
use Vanderlee\Comprehend\Extensions\Mail\Grammar\Rfc7208;

$ipv6 = new Rfc4291();
$address = $ipv6->rule('ipv6_address')->freeze()->parse('2001:db8::cd30');
$prefix = $ipv6->freeze()->parse('2001:db8::/32');
$spf = (new Rfc7208())->freeze()->parse('v=spf1 ip6:2001:db8::/32 include:example.org -all');

foreach ([$address, $prefix, $spf] as $result) {
    if (!$result->success) {
        throw new RuntimeException('Bundled RFC example failed to parse');
    }
    echo $result->rawText() . PHP_EOL;
}
