<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Vanderlee\Comprehend\Extensions\Abnf\Grammar\Rfc2234;
use Vanderlee\Comprehend\Extensions\Abnf\Grammar\Rfc4234;
use Vanderlee\Comprehend\Extensions\Abnf\Grammar\Rfc5234;
use Vanderlee\Comprehend\Extensions\Abnf\Grammar\Rfc7405;
use Vanderlee\Comprehend\Extensions\DateTime\Grammar\Rfc3339;
use Vanderlee\Comprehend\Extensions\DateTime\Grammar\Rfc9557;
use Vanderlee\Comprehend\Extensions\Encodings\Grammar\Rfc4648;
use Vanderlee\Comprehend\Extensions\HttpStructuredFields\Grammar\Rfc9651;
use Vanderlee\Comprehend\Extensions\Json\Grammar\Rfc6901;
use Vanderlee\Comprehend\Extensions\Json\Grammar\Rfc8259;
use Vanderlee\Comprehend\Extensions\Json\Grammar\Rfc9535;
use Vanderlee\Comprehend\Extensions\LanguageTag\Grammar\Rfc5646;
use Vanderlee\Comprehend\Extensions\Mail\Grammar\Rfc2045;
use Vanderlee\Comprehend\Extensions\Mail\Grammar\Rfc2046;
use Vanderlee\Comprehend\Extensions\Mail\Grammar\Rfc4408;
use Vanderlee\Comprehend\Extensions\Mail\Grammar\Rfc5321;
use Vanderlee\Comprehend\Extensions\Mail\Grammar\Rfc5322;
use Vanderlee\Comprehend\Extensions\Mail\Grammar\Rfc6376;
use Vanderlee\Comprehend\Extensions\Mail\Grammar\Rfc6531;
use Vanderlee\Comprehend\Extensions\Mail\Grammar\Rfc6532;
use Vanderlee\Comprehend\Extensions\Mail\Grammar\Rfc6854;
use Vanderlee\Comprehend\Extensions\Mail\Grammar\Rfc7208;
use Vanderlee\Comprehend\Extensions\Mail\Grammar\Rfc9989;
use Vanderlee\Comprehend\Extensions\Mail\Grammar\Rfc9990;
use Vanderlee\Comprehend\Extensions\Mail\Grammar\Rfc9991;
use Vanderlee\Comprehend\Extensions\Network\Grammar\Rfc1034;
use Vanderlee\Comprehend\Extensions\Network\Grammar\Rfc1035;
use Vanderlee\Comprehend\Extensions\Network\Grammar\Rfc1123;
use Vanderlee\Comprehend\Extensions\Network\Grammar\Rfc3513;
use Vanderlee\Comprehend\Extensions\Network\Grammar\Rfc3986;
use Vanderlee\Comprehend\Extensions\Network\Grammar\Rfc3987;
use Vanderlee\Comprehend\Extensions\Network\Grammar\Rfc4291;
use Vanderlee\Comprehend\Extensions\Network\Grammar\Rfc6570;
use Vanderlee\Comprehend\Extensions\Network\Grammar\Rfc8141;
use Vanderlee\Comprehend\Extensions\SemVer\Grammar\SemVer;
use Vanderlee\Comprehend\Extensions\Uuid\Grammar\Rfc9562;
use Vanderlee\Comprehend\Extensions\Xml\Grammar\Xml;
use Vanderlee\Comprehend\Extensions\Yaml\Grammar\Yaml;

final class LibraryFactoryCases
{
    public static function aliases(): array
    {
        return [
            'ipv4' => ['ipv4', Rfc3986::class, 'IPv4address', '192.0.2.1'],
            'ipv6' => ['ipv6', Rfc4291::class, 'ipv6_address', '2001:db8::1'],
            'hostname' => ['hostname', Rfc1123::class, 'hostname', 'example.org'],
            'uri' => ['uri', Rfc3986::class, 'URI', 'https://example.org/a'],
            'uriReference' => ['uriReference', Rfc3986::class, 'URI_reference', '../a?b'],
            'absoluteUri' => ['absoluteUri', Rfc3986::class, 'absolute_URI', 'https://example.org/a'],
            'iri' => ['iri', Rfc3987::class, 'IRI', 'https://example.org/é'],
            'urn' => ['urn', Rfc8141::class, 'urn', 'urn:example:test'],
            'uriTemplate' => ['uriTemplate', Rfc6570::class, 'uri_template', '/{path}'],
            'hostnameLabel' => ['hostnameLabel', Rfc1123::class, 'label', 'example'],
            'uriScheme' => ['uriScheme', Rfc3986::class, 'scheme', 'https'],
            'uriAuthority' => ['uriAuthority', Rfc3986::class, 'authority', 'example.org:443'],
            'uriHost' => ['uriHost', Rfc3986::class, 'host', 'example.org'],
            'uriPort' => ['uriPort', Rfc3986::class, 'port', '443'],
            'uriRelativePart' => ['uriRelativePart', Rfc3986::class, 'relative_part', '../a'],
            'uriPathAbempty' => ['uriPathAbempty', Rfc3986::class, 'path_abempty', '/a/b'],
            'uriSegment' => ['uriSegment', Rfc3986::class, 'segment', 'a%20b'],
            'uriQuery' => ['uriQuery', Rfc3986::class, 'query', 'a=b'],
            'uriFragment' => ['uriFragment', Rfc3986::class, 'fragment', 'section'],
            'uriPercentEncoded' => ['uriPercentEncoded', Rfc3986::class, 'pct_encoded', '%20'],
            'uriUnreserved' => ['uriUnreserved', Rfc3986::class, 'unreserved', 'A'],
            'iriUcschar' => ['iriUcschar', Rfc3987::class, 'ucschar', 'é'],
            'iriPrivateChar' => ['iriPrivateChar', Rfc3987::class, 'iprivate', ''],
            'emailAddress' => ['emailAddress', Rfc5322::class, 'addr_spec', 'a@example.org'],
            'mailbox' => ['mailbox', Rfc5322::class, 'mailbox', 'Alice <a@example.org>'],
            'mailboxList' => ['mailboxList', Rfc5322::class, 'mailbox_list', 'a@example.org, b@example.org'],
            'emailAddressList' => [
                'emailAddressList', Rfc5322::class, 'address_list', 'Friends: a@example.org;',
            ],
            'mailMessage' => ['mailMessage', Rfc5322::class, 'message', "Subject: hello\r\n\r\nbody"],
            'emailLocalPart' => ['emailLocalPart', Rfc5322::class, 'local_part', 'alice'],
            'emailDomain' => ['emailDomain', Rfc5322::class, 'domain', 'example.org'],
            'emailDotAtomText' => ['emailDotAtomText', Rfc5322::class, 'dot_atom_text', 'alice.smith'],
            'emailQuotedString' => ['emailQuotedString', Rfc5322::class, 'quoted_string', '"alice smith"'],
            'emailFoldingWhitespace' => ['emailFoldingWhitespace', Rfc5322::class, 'fws', "\r\n "],
            'emailCommentsAndFoldingWhitespace' => [
                'emailCommentsAndFoldingWhitespace', Rfc5322::class, 'cfws', '(hello)',
            ],
            'dateTime' => ['dateTime', Rfc3339::class, 'date_time', '2024-01-02T03:04:05Z'],
            'annotatedDateTime' => [
                'annotatedDateTime', Rfc9557::class, 'date_time_ext', '2024-01-02T03:04:05Z[UTC]',
            ],
            'dateTimeDate' => ['dateTimeDate', Rfc3339::class, 'full_date', '2024-01-02'],
            'dateTimeTime' => ['dateTimeTime', Rfc3339::class, 'full_time', '03:04:05Z'],
            'uuid' => ['uuid', Rfc9562::class, 'uuid', '550e8400-e29b-41d4-a716-446655440000'],
            'uuidUrn' => [
                'uuidUrn', Rfc9562::class, 'uuid_urn', 'urn:uuid:550e8400-e29b-41d4-a716-446655440000',
            ],
            'semver' => ['semver', SemVer::class, 'version', '1.2.3-alpha+build'],
            'languageTag' => ['languageTag', Rfc5646::class, 'language_tag', 'en-GB'],
            'base16' => ['base16', Rfc4648::class, 'base16', '4142'],
            'base32' => ['base32', Rfc4648::class, 'base32', 'MY======'],
            'base32Hex' => ['base32Hex', Rfc4648::class, 'base32hex', 'CO======'],
            'base64' => ['base64', Rfc4648::class, 'base64', 'YQ=='],
            'base64Url' => ['base64Url', Rfc4648::class, 'base64url', 'YQ=='],
            'base64Unpadded' => ['base64Unpadded', Rfc4648::class, 'base64_unpadded', 'YQ'],
            'base64UrlUnpadded' => ['base64UrlUnpadded', Rfc4648::class, 'base64url_unpadded', 'YQ'],
            'json' => ['json', Rfc8259::class, 'json_text', '{"a":1}'],
            'jsonPointer' => ['jsonPointer', Rfc6901::class, 'json_pointer', '/a~1b'],
            'jsonPath' => ['jsonPath', Rfc9535::class, 'jsonpath_query', '$.name'],
            'jsonString' => ['jsonString', Rfc8259::class, 'string', '"hello"'],
            'jsonNumber' => ['jsonNumber', Rfc8259::class, 'number', '-1.25e2'],
            'structuredFieldItem' => ['structuredFieldItem', Rfc9651::class, 'sf_item', '1'],
            'structuredFieldList' => ['structuredFieldList', Rfc9651::class, 'sf_list', '1, 2'],
            'structuredFieldDictionary' => [
                'structuredFieldDictionary', Rfc9651::class, 'sf_dictionary', 'a=1',
            ],
            'xmlDocument' => ['xmlDocument', Xml::class, 'document', '<root/>'],
            'yaml' => ['yaml', Yaml::class, 'stream', "name: value\n"],
        ];
    }

    public static function versions(): array
    {
        return [
            'rfc1034' => ['rfc1034', Rfc1034::class, 'ROOT', 'example.org'],
            'rfc1035' => ['rfc1035', Rfc1035::class, 'ROOT', 'example.org'],
            'rfc1123' => ['rfc1123', Rfc1123::class, 'ROOT', 'example.org'],
            'rfc3513' => ['rfc3513', Rfc3513::class, 'ROOT', '2001:db8::/32'],
            'rfc3986' => ['rfc3986', Rfc3986::class, 'URI', 'https://example.org/a'],
            'rfc3987' => ['rfc3987', Rfc3987::class, 'ROOT', 'https://example.org/é'],
            'rfc4291' => ['rfc4291', Rfc4291::class, 'ROOT', '2001:db8::/32'],
            'rfc6570' => ['rfc6570', Rfc6570::class, 'ROOT', '/{path}'],
            'rfc8141' => ['rfc8141', Rfc8141::class, 'ROOT', 'urn:example:test'],
            'rfc2045' => ['rfc2045', Rfc2045::class, 'ROOT', 'text/plain'],
            'rfc2046' => ['rfc2046', Rfc2046::class, 'ROOT', 'text/plain'],
            'rfc4408' => ['rfc4408', Rfc4408::class, 'ROOT', 'v=spf1 -all'],
            'rfc5321' => ['rfc5321', Rfc5321::class, 'ROOT', 'a@example.org'],
            'rfc5322' => ['rfc5322', Rfc5322::class, 'ROOT', 'a@example.org'],
            'rfc6376' => ['rfc6376', Rfc6376::class, 'ROOT', 'v=1; a=rsa-sha256; d=example.org; b=YWJj;'],
            'rfc6531' => ['rfc6531', Rfc6531::class, 'ROOT', 'a@example.org'],
            'rfc6532' => ['rfc6532', Rfc6532::class, 'ROOT', 'a@example.org'],
            'rfc6854' => ['rfc6854', Rfc6854::class, 'ROOT', 'a@example.org'],
            'rfc7208' => ['rfc7208', Rfc7208::class, 'ROOT', 'v=spf1 -all'],
            'rfc9989' => ['rfc9989', Rfc9989::class, 'ROOT', 'v=DMARC1; p=reject'],
            'rfc9990' => [
                'rfc9990', Rfc9990::class, 'ROOT',
                '<feedback xmlns="urn:ietf:params:xml:ns:dmarc-2.0">'
                    . '<report_metadata/><policy_published/><record/></feedback>',
            ],
            'rfc9991' => ['rfc9991', Rfc9991::class, 'ROOT', 'Identity-Alignment: dkim, spf'],
            'rfc3339' => ['rfc3339', Rfc3339::class, 'ROOT', '2024-01-02T03:04:05Z'],
            'rfc9557' => [
                'rfc9557', Rfc9557::class, 'ROOT', '2024-01-02T03:04:05Z[UTC]',
            ],
            'rfc9562' => ['rfc9562', Rfc9562::class, 'ROOT', '550e8400-e29b-41d4-a716-446655440000'],
            'rfc5646' => ['rfc5646', Rfc5646::class, 'ROOT', 'en-GB'],
            'rfc4648' => ['rfc4648', Rfc4648::class, 'ROOT', 'YQ=='],
            'rfc6901' => ['rfc6901', Rfc6901::class, 'ROOT', '/name'],
            'rfc8259' => ['rfc8259', Rfc8259::class, 'ROOT', '{"a":1}'],
            'rfc9535' => ['rfc9535', Rfc9535::class, 'ROOT', '$.name'],
            'rfc9651' => ['rfc9651', Rfc9651::class, 'ROOT', '1, 2'],
            'rfc2234' => ['rfc2234', Rfc2234::class, 'ROOT', "r = \"a\"\r\n"],
            'rfc4234' => ['rfc4234', Rfc4234::class, 'ROOT', "r = \"a\"\r\n"],
            'rfc5234' => ['rfc5234', Rfc5234::class, 'ROOT', "r = \"a\"\r\n"],
            'rfc7405' => ['rfc7405', Rfc7405::class, 'ROOT', "r = %s\"a\"\r\n"],
        ];
    }
}
