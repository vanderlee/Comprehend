<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Network\Network;

$syntax = C::syntax()->extend(new Network());
$host = $syntax->rfc3986(rule: 'host')->parse('example.org');
$digits = C::rfc5234(rule: 'DIGIT')->some()->parse('42');
$email = C::emailAddressList()->parse('Friends: alice@example.org;');
$version = C::semver()->parse('1.2.3-alpha+build');
$timestamp = C::annotatedDateTime()->parse('2024-01-02T03:04:05Z[UTC]');
$json = C::text('@')->ignoreThen(C::json())->parse('@{"answer":42}');
$yaml = C::yaml()->parse("name: value\n");
foreach ([$host, $digits, $email, $version, $timestamp, $yaml] as $result) {
    if (!$result->success || $result->value() !== $result->rawText()) {
        throw new RuntimeException('Specification factory or textual value changed');
    }
}
if (!$json->success || $json->value() !== '{"answer":42}' || $json->rawText() !== '@{"answer":42}') {
    throw new RuntimeException('Value selection lost the complete source span');
}
if (!C::rfc4408()->parse('v=spf1 -all')->success) {
    throw new RuntimeException('Explicit historical RFC grammar is unavailable');
}

echo "Subject factories, versioned subrules, fluent composition and original text agree.\n";
