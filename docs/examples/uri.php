<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Vanderlee\Comprehend\Extensions\Library\Rfc3986;

$rules = new Rfc3986();
$grammar = $rules->rule('URI')->freeze();
foreach (
    [
        'https://example.com/a%af?q=%aF#section',
        'http://[2001:db8::1]:8080/a',
        'http://[::ffff:192.0.2.128]/',
        'http://[Vf.example]/',
        'http://1.2.3.256/',
        'mailto:alice@example.com',
    ] as $input
) {
    $result = $grammar->parse($input);
    if (!$result->success || $result->rawText() !== $input || $result->consumedByteLength !== strlen($input)) {
        throw new RuntimeException('URI source round trip failed: ' . $input);
    }
}
foreach (['http://[1::2::3]/', 'https://example.com/%a', 'https://example.com/a b'] as $input) {
    if ($grammar->parse($input)->success) {
        throw new RuntimeException('Malformed URI was accepted: ' . $input);
    }
}
if (!$rules->rule('path')->parse('relative/path')->success) {
    throw new RuntimeException('Standalone URI path failed');
}
echo "URI IPv6, escapes, host alternatives and source round trips passed.\n";
