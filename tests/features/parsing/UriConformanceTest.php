<?php

declare(strict_types=1);

namespace Tests\features\parsing;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Library\Rfc3986;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;

uses(TestCase::class);

test('URI grammar accepts RFC IPv6 alternatives and preserves original source bytes', function (string $address) {
    static $grammar = null;
    $grammar ??= (new Rfc3986())->ref('URI')->freeze();
    $input = 'http://[' . $address . ']:8080/a%af?q=%aF#fragment';
    foreach ([new ByteSource($input), new Utf8Source($input)] as $source) {
        $result = $grammar->parseAll($source);
        $this->assertTrue($result->success, $input);
        $this->assertSame($input, $result->rawText());
        $this->assertSame(strlen($input), $result->consumedByteLength);
        $components = [];
        foreach ($result->ast()->descendants() as $node) {
            if ($node->name === 'IPv6address' || $node->name === 'scheme') {
                $components[$node->name] = $node->rawText();
            }
        }
        $this->assertSame($address, $components['IPv6address']);
        $this->assertSame('http', $components['scheme']);
    }
})->with([
    '1:2:3:4:5:6:7:8', '::2:3:4:5:6:7:8', '1::3:4:5:6:7:8', '1:2::4:5:6:7:8',
    '1:2:3::5:6:7:8', '1:2:3:4::6:7:8', '1:2:3:4:5::7:8', '1:2:3:4:5:6::8',
    '1:2:3:4:5:6:7::', '::', '::1', '2001:db8::1', '2001:DB8::1', '::ffff:192.0.2.128',
    '1:2:3:4:5:6:192.0.2.1',
]);

test('URI grammar rejects malformed addresses and escaped bytes without partial success', function (string $input) {
    static $grammar = null;
    $grammar ??= (new Rfc3986())->ref('URI')->freeze();
    $result = $grammar->parseAll($input);
    $this->assertFalse($result->success, $input);
    $this->assertSame(0, $result->consumedByteLength);
    $this->assertNotNull($result->diagnostic);
})->with([
    'http://[1:2:3:4:5:6:7:8:9]/', 'http://[1:2:3:4:5:6:7]/', 'http://[1::2::3]/',
    'http://[1:2:3:4:5:6:7:8::]/', 'http://[12345::]/', 'http://[::ffff:256.0.0.1]/',
    'http://[::ffff:192.00.2.1]/', 'http://[2001:db8::1', 'http://[]/', 'http://[v1.]/',
    'https://example.com/%', 'https://example.com/%a', 'https://example.com/%GG',
    'https://example.com/a b', 'https://example.com/café', '1http://example.com',
]);

test('URI generic host and empty port syntax remains distinct from scheme-specific policy', function (string $input) {
    $result = (new Rfc3986())->rule('URI')->parse($input);
    $this->assertTrue($result->success, $input);
    $this->assertSame($input, $result->rawText());
})->with([
    'http://1.2.3.256/', 'http://1.2.3.4example/', 'http://1.2.3.4%af/',
    'http://example.com:/', 'http://@example.com/', 'http://[Vf.example]/',
    'file:///etc/hosts', 'urn:example:animal', 'scheme:', 'https://example.com/%aF',
]);

test('standalone path alternatives consume their complete RFC path forms', function (string $input) {
    $result = (new Rfc3986())->rule('path')->parse($input);
    $this->assertTrue($result->success, $input);
    $this->assertSame($input, $result->rawText());
})->with(['', '/', '//example/path', '/a/b', 'a/b', 'a:b/c']);

test('optimized lexical rules retain named rule overrides and live deferred redefinitions', function () {
    $rules = new Rfc3986(['unreserved' => C::text('x'), 'h16' => C::text('g')]);
    $segment = $rules->rule('segment_nz');
    $this->assertTrue($segment->parse('xxx')->success);
    $this->assertFalse($segment->parse('abc')->success);
    $this->assertTrue($rules->rule('IPv6address')->parse('g::')->success);
    $old = $segment->freeze();
    $rules->define('unreserved', C::text('z'));
    $this->assertTrue($segment->parse('zzz')->success);
    $this->assertFalse($segment->parse('xxx')->success);
    $this->assertTrue($old->parse('xxx')->success);
});
