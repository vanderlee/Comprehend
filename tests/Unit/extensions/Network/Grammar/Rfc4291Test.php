<?php

declare(strict_types=1);

namespace Tests\Unit\extensions\Network\Grammar;

use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Network\Grammar\Rfc4291;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;

test('rfc4291 recognizes addresses and prefixes', function (string $rule, string $input, bool $accepted): void {
    $grammar = (new Rfc4291())->rule($rule)->freeze();
    foreach ([new ByteSource($input), new Utf8Source($input)] as $source) {
        $result = $grammar->parse($source);
        expect($result->success)->toBe($accepted);
        if ($accepted) {
            expect($result->rawText())->toBe($input);
            expect($result->consumedByteLength)->toBe(strlen($input));
        }
    }
})->with([
    ['ipv6_address', 'ABCD:EF01:2345:6789:ABCD:EF01:2345:6789', true],
    ['ipv6_address', '2001:DB8::8:800:200C:417A', true],
    ['ipv6_address', '2001:db8::cd30', true],
    ['ipv6_address', 'FF01::101', true],
    ['ipv6_address', '::1', true],
    ['ipv6_address', '::', true],
    ['ipv6_address', '::FFFF:129.144.52.38', true],
    ['ipv6_address', '2001:db8::192.0.2.1', true],
    ['ipv6_address', '1:2:3:4:5:6:7::', true],
    ['ipv6_address', '1::2:3:4:5:6:7', true],
    ['ipv6_address', '::1:2:3:4:5:6:7', true],
    ['ipv6_address', '0:0:0:0:0:FFFF:129.144.52.38', true],
    ['ipv6_address', '2001::db8::1', false],
    ['ipv6_address', '1:2:3:4:5:6:7:8:9', false],
    ['ipv6_address', '1:2:3:4:5:6:7:8::', false],
    ['ipv6_address', '1:2:3:4:5:6::192.0.2.1', false],
    ['ipv6_address', '1:2:3:4:5:6:7', false],
    ['ipv6_address', '12345::', false],
    ['ipv6_address', '::ffff:256.0.0.1', false],
    ['ipv6_address', 'fe80::1%eth0', false],
    ['ipv6', '2001:0DB8:0:CD30::/60', true],
    ['ipv6', '::/0', true],
    ['ipv6', '::1/128', true],
    ['ipv6', '::1/129', false],
    ['ipv6', '::1/-1', false],
    ['ipv6', '::1', false],
])->group('library', 'rfc');

test('rfc4291 root and overrides use the named rules', function (): void {
    $rfc = new Rfc4291(['prefix_length' => C::text('64')]);
    expect($rfc->groupName())->toBe('Rfc4291');
    expect($rfc->freeze()->parse('::/64')->success)->toBeTrue();
    expect($rfc->freeze()->parse('::/128')->success)->toBeFalse();
})->group('library', 'rfc');

test('rfc4291 preserves byte offsets when matching an address prefix', function (): void {
    $result = (new Rfc4291())->rule('ipv6_address')->matchPrefix(new ByteSource('xx2001:db8::1 tail'), 2);
    expect($result->success)->toBeTrue();
    expect($result->rawText())->toBe('2001:db8::1');
    expect($result->consumedByteLength)->toBe(11);
})->group('library', 'rfc');
