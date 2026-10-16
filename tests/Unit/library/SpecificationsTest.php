<?php

declare(strict_types=1);

namespace Tests\Src\Library;

use Vanderlee\Comprehend\Library\AbstractSpecification;
use Vanderlee\Comprehend\Library\Rfc3987;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;

test('specification rules recognize complete source spans', function (
    string $class,
    string $rule,
    string $input,
    bool $accepted,
): void {
    $name = 'Vanderlee\\Comprehend\\Library\\' . $class;
    $specification = new $name();
    expect($specification)->toBeInstanceOf(AbstractSpecification::class);
    $grammar = $rule === 'ROOT' ? $specification->freeze() : $specification->rule($rule)->freeze();
    foreach ([new ByteSource($input), new Utf8Source($input)] as $source) {
        $result = $grammar->parse($source);
        expect($result->success)->toBe($accepted, $class . ' ' . $rule . ': ' . $input);
        if ($accepted) {
            expect($result->rawText())->toBe($input);
            expect($result->consumedByteLength)->toBe(strlen($input));
        }
    }
})->with([
    ['SemVer', 'ROOT', '1.2.3-alpha.1+build.007', true],
    ['SemVer', 'ROOT', '1.2.3-01', false],
    ['SemVer', 'ROOT', '01.2.3', false],
    ['SemVer', 'ROOT', '1.2', false],
    ['Rfc8259', 'ROOT', ' {"a": [true, null, -1.25e+2, "\\uD834\\uDD1E"]} ', true],
    ['Rfc8259', 'ROOT', '[1,]', false],
    ['Rfc8259', 'ROOT', '{"a":01}', false],
    ['Rfc8259', 'ROOT', '"hello' . "\n" . 'world"', false],
    ['Rfc8259', 'ROOT', '"é"', true],
    ['Rfc6901', 'ROOT', '', true],
    ['Rfc6901', 'ROOT', '/a~1b/~0key/0', true],
    ['Rfc6901', 'ROOT', '/bad~2', false],
    ['Rfc6901', 'uri_fragment', '#/a%20b', true],
    ['Rfc6901', 'uri_fragment', '#foo', false],
    ['Rfc6901', 'uri_fragment', '#/bad~2', false],
    ['Rfc3339', 'ROOT', '2024-02-29T23:59:60.123+01:30', true],
    ['Rfc3339', 'ROOT', '2023-02-29T12:00:00Z', false],
    ['Rfc3339', 'ROOT', '2024-01-01T24:00:00Z', false],
    ['Rfc3339', 'ROOT', '2024-01-01t00:00:00z', true],
    ['Rfc3339', 'ROOT', '0000-02-29T00:00:00Z', true],
    ['Rfc9557', 'ROOT', '2024-01-01T12:00:00Z[Europe/Amsterdam][u-ca=hebrew]', true],
    ['Rfc9557', 'ROOT', '2024-01-01T12:00:00Z[!+01:00]', true],
    ['Rfc9557', 'ROOT', '2024-01-01T12:00:00Z[Europe/..]', false],
    ['Rfc9562', 'ROOT', '01890f3e-7b0d-7cc2-98c4-dc0c0c07398f', true],
    ['Rfc9562', 'ROOT', '00000000-0000-0000-0000-000000000000', true],
    ['Rfc9562', 'ROOT', '{01890f3e-7b0d-7cc2-98c4-dc0c0c07398f}', false],
    ['Rfc4648', 'base64', 'Zg==', true],
    ['Rfc4648', 'base64', 'Zm8=', true],
    ['Rfc4648', 'base64', 'Zm9v', true],
    ['Rfc4648', 'base64', 'Zh==', false],
    ['Rfc4648', 'base64', 'Zg', false],
    ['Rfc4648', 'base64url_unpadded', 'Zg', true],
    ['Rfc4648', 'base32', 'MY======', true],
    ['Rfc4648', 'base32', 'MZXQ====', true],
    ['Rfc4648', 'base32', 'MZXW6===', true],
    ['Rfc4648', 'base32', 'MZXW6YQ=', true],
    ['Rfc4648', 'base32', 'MZXW6YTB', true],
    ['Rfc4648', 'base32', 'mzxw6===', true],
    ['Rfc4648', 'base16', 'deadBEEF', true],
    ['Rfc5646', 'ROOT', 'en-Latn-US', true],
    ['Rfc5646', 'ROOT', 'zh-cmn-Hans-CN', true],
    ['Rfc5646', 'ROOT', 'de-DE-u-co-phonebk-x-private', true],
    ['Rfc5646', 'ROOT', 'i-klingon', true],
    ['Rfc5646', 'ROOT', 'en_US', false],
    ['Rfc5646', 'ROOT', 'sl-rozaj-rozaj', false],
    ['Rfc5646', 'ROOT', 'en-a-foo-a-bar', false],
    ['Rfc1123', 'ROOT', '3com.example.', true],
    ['Rfc1123', 'ROOT', '-bad.example', false],
    ['Rfc1123', 'ROOT', 'bad-.example', false],
    ['Rfc1035', 'ROOT', '_dmarc.example.org.', true],
    ['Rfc1035', 'ROOT', 'escaped\\.label.example.', true],
    ['Rfc1035', 'ROOT', 'bad..example', false],
    ['Rfc1035', 'ROOT', 'bad\\999.example', false],
    ['Rfc1035', 'zone_file', "$" . "ORIGIN example.org.\n@ IN A 192.0.2.1\nwww 3600 IN AAAA 2001:db8::1\n", true],
    ['Rfc1035', 'zone_record', "example.org. IN MX 10 mail.example.org.\n", true],
    ['Rfc1035', 'zone_record', "example.org. IN A 999.0.0.1\n", false],
    ['Rfc1035', 'zone_file', "this is not a resource record\n", false],
    ['Rfc1035', 'zone_record', "@ IN SOA ( ns.example. admin.example.\n 1 2 3 4 5 )\n", true],
    ['Rfc6570', 'ROOT', 'https://example.org/{+path}{?q,limit:3}', true],
    ['Rfc6570', 'ROOT', '{var:0001}', false],
    ['Rfc6570', 'ROOT', '{var', false],
    ['Rfc8141', 'ROOT', 'urn:example:a/b?+resolve?=query#fragment', true],
    ['Rfc8141', 'ROOT', 'urn:x:a', false],
    ['Rfc5322', 'ROOT', 'John (nested (comment)) Doe <john@example.org>', true],
    ['Rfc5322', 'ROOT', '"a b"@example.org', true],
    ['Rfc5322', 'ROOT', 'a..b@example.org', false],
    ['Rfc5322', 'ROOT', 'a@example.org trailing', false],
    ['Rfc5322', 'address', 'Friends: a@example.org, b@example.org;', true],
    ['Rfc5322', 'address', 'Undisclosed:;', true],
    ['Rfc5322', 'from', "From: Group:;\r\n", false],
    ['Rfc6854', 'from', "From: Group:;\r\n", true],
    ['Rfc6532', 'ROOT', 'Émilie <émilie@例え.example>', true],
    ['Rfc5322', 'ROOT', 'émilie@example.org', false],
    ['Rfc5321', 'ROOT', '"a b"@[IPv6:2001:db8::1]', true],
    ['Rfc5321', 'ROOT', 'a (comment)@example.org', false],
    ['Rfc5321', 'reverse_path', '<>', true],
    ['Rfc5321', 'mail', "MAIL FROM:<a@example.org> SIZE=123\r\n", true],
    ['Rfc5321', 'command', "EHLO example.org\r\n", true],
    ['Rfc5321', 'command', "QUIT\r\n", true],
    ['Rfc5321', 'reply', "250-first\r\n250 second\r\n", true],
    ['Rfc5321', 'reply', "250-first\r\n550 second\r\n", false],
    ['Rfc6531', 'ROOT', '用户@例え.example', true],
    ['Rfc6531', 'ROOT', '用户@-例え.example', false],
    ['Rfc6531', 'ROOT', '用户@例え/path', false],
    ['Rfc2045', 'ROOT', 'text/plain; charset="utf-8"', true],
    ['Rfc2045', 'ROOT', 'text', false],
    ['Rfc2046', 'boundary', 'simple boundary', true],
    ['Rfc2046', 'boundary', 'bad ', false],
    ['Rfc6376', 'ROOT', 'v=1; a=rsa-sha256; d=example.org; b=YWJj;', true],
    ['Rfc6376', 'ROOT', 'v=1; v=1', false],
    ['Rfc6376', 'ROOT', "v=1; b=abc\n def", false],
    ['Rfc9989', 'ROOT', 'v=DMARC1; p=reject; rua=mailto:reports@example.org', true],
    ['Rfc9989', 'ROOT', 'v=dmarc1; p=reject', false],
    ['Rfc9651', 'sf_item', '42;foo="bar"', true],
    ['Rfc9651', 'sf_list', '("a" "b");q=0.5, token;flag', true],
    ['Rfc9651', 'sf_dictionary', 'a=1, b, c=(2 3)', true],
    ['Rfc9651', 'sf_item', '%"caf%c3%a9"', true],
    ['Rfc9651', 'sf_item', '?2', false],
    ['Rfc9651', 'sf_item', '1000000000000000', false],
    ['Rfc9535', 'ROOT', '$.store.book[0].title', true],
    ['Rfc9535', 'ROOT', '$..book[?@.price < 10 && @.category == "fiction"]', true],
    ['Rfc9535', 'ROOT', '$[0:10:2, -1, "name"]', true],
    ['Rfc9535', 'ROOT', '$[?length(@.name) >= 3]', true],
    ['Rfc9535', 'ROOT', '$[01]', false],
    ['Rfc9535', 'ROOT', '$[?@.x = 1]', false],
    ['Xml', 'ROOT', '<?xml version="1.0"?><root a="1"><子>é &amp; &#x1F600;</子><![CDATA[a<b]]></root>', true],
    ['Xml', 'ROOT', '<a><b/></a>', true],
    ['Xml', 'ROOT', '<a><b></a>', false],
    ['Xml', 'ROOT', '<a x="1" x="2"/>', false],
    ['Xml', 'ROOT', '<a>&#0;</a>', false],
    ['Xml', 'ROOT', '<a><!-- bad--comment --></a>', false],
    ['Xml', 'ROOT', '<a>]]></a>', false],
    ['Xml', 'ROOT', '<!DOCTYPE a [<!ELEMENT a (#PCDATA)><!ENTITY x "hello">]><a>&x;</a>', true],
    ['Xml', 'ROOT', '<a/><b/>', false],
    ['Xml', 'ROOT', '<a>&undefined;</a>', false],
    ['Xml', 'ROOT', '<!DOCTYPE a><b/>', false],
    ['Xml', 'ROOT', '<!DOCTYPE a [<!ELEMENT a b>]><a/>', false],
    ['Xml', 'ROOT', '<!DOCTYPE a [<!ELEMENT a (#PCDATA|b)>]><a/>', false],
    ['Xml', 'ROOT', '<a><!-- &undefined; --><![CDATA[&undefined;]]></a>', true],
    ['Yaml', 'ROOT', "name: Comprehend\nversion: 1.2.3\n", true],
    ['Yaml', 'ROOT', "items:\n  - one\n  - two\n", true],
    ['Yaml', 'ROOT', "- name: first\n  enabled: true\n- name: second\n  enabled: false\n", true],
    ['Yaml', 'ROOT', "flow: [one, {two: 2}, null]\n", true],
    ['Yaml', 'ROOT', "%YAML 1.2\n---\nname: test\n...\n---\n- second\n", true],
    ['Yaml', 'ROOT', "text: |-\n  hello\n  world\nnext: value\n", true],
    ['Yaml', 'ROOT', "defaults: &defaults\n  enabled: true\ncopy: *defaults\n", true],
    ['Yaml', 'ROOT', "'quoted': \"unicode \\u00e9\"\n", true],
    ['Yaml', 'ROOT', "a:\n  b: 1\n c: 2\n", false],
    ['Yaml', 'ROOT', "a:\n\tb: 1\n", false],
    ['Yaml', 'ROOT', "a: [1, 2\n", false],
    ['Yaml', 'ROOT', "a: \"bad\\q\"\n", false],
    ['Yaml', 'single_quoted', "'bad\x01'", false],
    ['Yaml', 'plain_scalar', "bad\u{80}", false],
    ['Rfc9991', 'ROOT', 'Identity-Alignment: dkim, spf', true],
    ['Rfc9991', 'ROOT', 'Identity-Alignment: dkim, dkim', false],
    ['Rfc9991', 'ROOT', 'Identity-Alignment: none, spf', false],
    ['Rfc9990', 'report_filename', 'receiver.example!sender.example!1!2.xml.gz', true],
    ['Rfc9990', 'ROOT', '<feedback xmlns="urn:ietf:params:xml:ns:dmarc-2.0">'
        . '<report_metadata></report_metadata><policy_published></policy_published><record></record></feedback>', true],
    ['Rfc9990', 'ROOT', '<d:feedback xmlns:d="urn:ietf:params:xml:ns:dmarc-2.0">'
        . '<d:report_metadata/><d:policy_published/><d:record/></d:feedback>', true],
    ['Rfc9990', 'ROOT', '<other><!-- <feedback xmlns="urn:ietf:params:xml:ns:dmarc-2.0">'
        . '<report_metadata><policy_published><record> --></other>', false],
    ['Rfc9990', 'ROOT', '<feedback xmlns="urn:ietf:params:xml:ns:dmarc-2.0">'
        . '<record/><report_metadata/><policy_published/></feedback>', false],
])->group('library', 'specifications');

test('internationalized resource identifiers preserve Unicode spans', function (): void {
    $iri = (new Rfc3987())->freeze();
    foreach ([new ByteSource('https://例え.example/é?q=世界'), new Utf8Source('https://例え.example/é?q=世界')] as $source) {
        $result = $iri->parse($source);
        expect($result->success)->toBeTrue();
        expect($result->rawText())->toBe('https://例え.example/é?q=世界');
    }
})->group('library', 'specifications');

test('Unicode specification roots reject malformed UTF-8 in byte sources', function (): void {
    $inputs = [
        'Rfc8259' => '"bad' . "\xff" . '"',
        'Yaml' => 'key: ' . "\xff",
        'Xml' => '<root>' . "\xff" . '</root>',
        'Rfc6532' => "\xff" . '@example.org',
    ];
    foreach ($inputs as $class => $input) {
        $name = 'Vanderlee\\Comprehend\\Library\\' . $class;
        expect((new $name())->freeze()->parse(new ByteSource($input))->success)->toBeFalse();
    }
})->group('library', 'specifications');
