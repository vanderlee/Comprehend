<?php

declare(strict_types=1);

namespace Tests\Src\Library;

use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Library\Rfc4408;
use Vanderlee\Comprehend\Extensions\Library\Rfc7208;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;

test('rfc7208 recognizes complete SPF records', function (string $input, bool $accepted): void {
    $grammar = (new Rfc7208())->freeze();
    foreach ([new ByteSource($input), new Utf8Source($input)] as $source) {
        $result = $grammar->parse($source);
        expect($result->success)->toBe($accepted);
        if ($accepted) {
            expect($result->rawText())->toBe($input);
            expect($result->consumedByteLength)->toBe(strlen($input));
        }
    }
})->with([
    ['v=spf1', true],
    ['v=spf1 -all  ', true],
    ['V=SPF1 A MX PTR -ALL', true],
    ['v=spf1 include:example.com include:example.net -all', true],
    ['v=spf1 redirect=example.org', true],
    ['v=spf1 mx:mail.example.com/24//64 -all', true],
    ['v=spf1 a//128 ptr:example.org ?all', true],
    ['v=spf1 ip4:192.0.2.0/24 ip6:2001:db8::/32 -all', true],
    ['v=spf1 ip4:0.0.0.0/0 ip4:255.255.255.255/32 ip6:::1/128', true],
    ['v=spf1 exists:%{ir}.%{v}._spf.%{d2} -all exp=explain._spf.%{d}', true],
    ['v=spf1 INCLUDE:%{D} EXP=why.example.com future=%%-%_-%-', true],
    ['v=spf1 include:example.xn--p1ai -all', true],
    ['v=spf1 include:example.com. -all', true],
    ['v=spf1 future=', true],
    ['v=spf1 include:example.com/path.example.net -all', true],
    ['v=spf2 -all', false],
    ["v=spf1\t-all", false],
    ['v=spf1 invalid', false],
    ['v=spf1 ip4:192.0.2.1/33', false],
    ['v=spf1 ip6:::1/129', false],
    ['v=spf1 ip4:192.0.2.1/01', false],
    ['v=spf1 a/33', false],
    ['v=spf1 mx:example.com/24//129', false],
    ['v=spf1 ip4:192.0.2.256', false],
    ['v=spf1 ip4:192.00.2.1', false],
    ['v=spf1 include:example.123', false],
    ['v=spf1 redirect=', false],
    ['v=spf1 EXP=example.123', false],
    ['v=spf1 future=%{z}', false],
    ['v=spf1 include:example.com%bad', false],
])->group('library', 'rfc');

test('rfc7208 macro and network rules can be imported', function (string $rule, string $input, bool $accepted): void {
    $grammar = (new Rfc7208())->rule($rule)->freeze();
    expect($grammar->parse($input)->success)->toBe($accepted);
})->with([
    ['macro_string', '', true],
    ['macro_string', '%{ir}.%{v}._spf.%{D2R-}', true],
    ['explain_string', '', true],
    ['explain_string', 'Denied %{I} %% message', true],
    ['macro_string', '%{z}', false],
    ['domain_spec', '%{d}', true],
    ['domain_spec', 'example.xn--p1ai', true],
    ['domain_spec', 'example.123', false],
    ['domain_spec', 'example.-com', false],
    ['domain_spec', 'example.com-', false],
    ['toplabel', 'xn--p1ai', true],
    ['toplabel', '123', false],
    ['ip4_cidr_length', '/32', true],
    ['ip4_cidr_length', '/33', false],
    ['ip6_cidr_length', '/128', true],
    ['ip6_cidr_length', '/129', false],
    ['ip6_network', '2001:db8::cd30', true],
])->group('library', 'rfc');

test('rfc7208 overrides stay local and preserve the legacy SPF grammar', function (): void {
    $rfc = new Rfc7208(['all' => C::text('custom')]);
    expect($rfc->groupName())->toBe('Rfc7208');
    expect($rfc->freeze()->parse('v=spf1 custom')->success)->toBeTrue();
    expect((new Rfc7208())->freeze()->parse('v=spf1 custom')->success)->toBeFalse();
    expect((new Rfc4408())->rule('ip4_cidr_length')->parse('/33')->success)->toBeTrue();
})->group('library', 'rfc');
