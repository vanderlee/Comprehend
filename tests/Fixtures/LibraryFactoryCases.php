<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Vanderlee\Comprehend\Extensions\Library;

final class LibraryFactoryCases
{
    public static function aliases(): array
    {
        return [
            'ipv4' => ['ipv4', Library\Rfc3986::class, 'IPv4address', '192.0.2.1'],
            'ipv6' => ['ipv6', Library\Rfc4291::class, 'ipv6_address', '2001:db8::1'],
            'hostname' => ['hostname', Library\Rfc1123::class, 'hostname', 'example.org'],
            'uri' => ['uri', Library\Rfc3986::class, 'URI', 'https://example.org/a'],
            'uriReference' => ['uriReference', Library\Rfc3986::class, 'URI_reference', '../a?b'],
            'absoluteUri' => ['absoluteUri', Library\Rfc3986::class, 'absolute_URI', 'https://example.org/a'],
            'iri' => ['iri', Library\Rfc3987::class, 'IRI', 'https://example.org/é'],
            'urn' => ['urn', Library\Rfc8141::class, 'urn', 'urn:example:test'],
            'uriTemplate' => ['uriTemplate', Library\Rfc6570::class, 'uri_template', '/{path}'],
            'hostnameLabel' => ['hostnameLabel', Library\Rfc1123::class, 'label', 'example'],
            'uriScheme' => ['uriScheme', Library\Rfc3986::class, 'scheme', 'https'],
            'uriAuthority' => ['uriAuthority', Library\Rfc3986::class, 'authority', 'example.org:443'],
            'uriHost' => ['uriHost', Library\Rfc3986::class, 'host', 'example.org'],
            'uriPort' => ['uriPort', Library\Rfc3986::class, 'port', '443'],
            'uriRelativePart' => ['uriRelativePart', Library\Rfc3986::class, 'relative_part', '../a'],
            'uriPathAbempty' => ['uriPathAbempty', Library\Rfc3986::class, 'path_abempty', '/a/b'],
            'uriSegment' => ['uriSegment', Library\Rfc3986::class, 'segment', 'a%20b'],
            'uriQuery' => ['uriQuery', Library\Rfc3986::class, 'query', 'a=b'],
            'uriFragment' => ['uriFragment', Library\Rfc3986::class, 'fragment', 'section'],
            'uriPercentEncoded' => ['uriPercentEncoded', Library\Rfc3986::class, 'pct_encoded', '%20'],
            'uriUnreserved' => ['uriUnreserved', Library\Rfc3986::class, 'unreserved', 'A'],
            'iriUcschar' => ['iriUcschar', Library\Rfc3987::class, 'ucschar', 'é'],
            'iriPrivateChar' => ['iriPrivateChar', Library\Rfc3987::class, 'iprivate', ''],
            'emailAddress' => ['emailAddress', Library\Rfc5322::class, 'addr_spec', 'a@example.org'],
            'mailbox' => ['mailbox', Library\Rfc5322::class, 'mailbox', 'Alice <a@example.org>'],
            'mailboxList' => ['mailboxList', Library\Rfc5322::class, 'mailbox_list', 'a@example.org, b@example.org'],
            'emailAddressList' => ['emailAddressList', Library\Rfc5322::class, 'address_list', 'Friends: a@example.org;'],
            'mailMessage' => ['mailMessage', Library\Rfc5322::class, 'message', 'Subject: hello' . "\r" . '' . "\n" . '' . "\r" . '' . "\n" . 'body'],
            'emailLocalPart' => ['emailLocalPart', Library\Rfc5322::class, 'local_part', 'alice'],
            'emailDomain' => ['emailDomain', Library\Rfc5322::class, 'domain', 'example.org'],
            'emailDotAtomText' => ['emailDotAtomText', Library\Rfc5322::class, 'dot_atom_text', 'alice.smith'],
            'emailQuotedString' => ['emailQuotedString', Library\Rfc5322::class, 'quoted_string', '"alice smith"'],
            'emailFoldingWhitespace' => ['emailFoldingWhitespace', Library\Rfc5322::class, 'fws', '' . "\r" . '' . "\n" . ' '],
            'emailCommentsAndFoldingWhitespace' => ['emailCommentsAndFoldingWhitespace', Library\Rfc5322::class, 'cfws', '(hello)'],
            'dateTime' => ['dateTime', Library\Rfc3339::class, 'date_time', '2024-01-02T03:04:05Z'],
            'annotatedDateTime' => ['annotatedDateTime', Library\Rfc9557::class, 'date_time_ext', '2024-01-02T03:04:05Z[UTC]'],
            'dateTimeDate' => ['dateTimeDate', Library\Rfc3339::class, 'full_date', '2024-01-02'],
            'dateTimeTime' => ['dateTimeTime', Library\Rfc3339::class, 'full_time', '03:04:05Z'],
            'uuid' => ['uuid', Library\Rfc9562::class, 'uuid', '550e8400-e29b-41d4-a716-446655440000'],
            'uuidUrn' => ['uuidUrn', Library\Rfc9562::class, 'uuid_urn', 'urn:uuid:550e8400-e29b-41d4-a716-446655440000'],
            'semver' => ['semver', Library\SemVer::class, 'version', '1.2.3-alpha+build'],
            'languageTag' => ['languageTag', Library\Rfc5646::class, 'language_tag', 'en-GB'],
            'base16' => ['base16', Library\Rfc4648::class, 'base16', '4142'],
            'base32' => ['base32', Library\Rfc4648::class, 'base32', 'MY======'],
            'base32Hex' => ['base32Hex', Library\Rfc4648::class, 'base32hex', 'CO======'],
            'base64' => ['base64', Library\Rfc4648::class, 'base64', 'YQ=='],
            'base64Url' => ['base64Url', Library\Rfc4648::class, 'base64url', 'YQ=='],
            'base64Unpadded' => ['base64Unpadded', Library\Rfc4648::class, 'base64_unpadded', 'YQ'],
            'base64UrlUnpadded' => ['base64UrlUnpadded', Library\Rfc4648::class, 'base64url_unpadded', 'YQ'],
            'json' => ['json', Library\Rfc8259::class, 'json_text', '{"a":1}'],
            'jsonPointer' => ['jsonPointer', Library\Rfc6901::class, 'json_pointer', '/a~1b'],
            'jsonPath' => ['jsonPath', Library\Rfc9535::class, 'jsonpath_query', '$.name'],
            'jsonString' => ['jsonString', Library\Rfc8259::class, 'string', '"hello"'],
            'jsonNumber' => ['jsonNumber', Library\Rfc8259::class, 'number', '-1.25e2'],
            'structuredFieldItem' => ['structuredFieldItem', Library\Rfc9651::class, 'sf_item', '1'],
            'structuredFieldList' => ['structuredFieldList', Library\Rfc9651::class, 'sf_list', '1, 2'],
            'structuredFieldDictionary' => ['structuredFieldDictionary', Library\Rfc9651::class, 'sf_dictionary', 'a=1'],
            'xmlDocument' => ['xmlDocument', Library\Xml::class, 'document', '<root/>'],
            'yaml' => ['yaml', Library\Yaml::class, 'stream', 'name: value' . "\n" . ''],
        ];
    }

    public static function versions(): array
    {
        return [
            'rfc1034' => ['rfc1034', Library\Rfc1034::class, 'ROOT', 'example.org'],
            'rfc1035' => ['rfc1035', Library\Rfc1035::class, 'ROOT', 'example.org'],
            'rfc1123' => ['rfc1123', Library\Rfc1123::class, 'ROOT', 'example.org'],
            'rfc3513' => ['rfc3513', Library\Rfc3513::class, 'ROOT', '2001:db8::/32'],
            'rfc3986' => ['rfc3986', Library\Rfc3986::class, 'URI', 'https://example.org/a'],
            'rfc3987' => ['rfc3987', Library\Rfc3987::class, 'ROOT', 'https://example.org/é'],
            'rfc4291' => ['rfc4291', Library\Rfc4291::class, 'ROOT', '2001:db8::/32'],
            'rfc6570' => ['rfc6570', Library\Rfc6570::class, 'ROOT', '/{path}'],
            'rfc8141' => ['rfc8141', Library\Rfc8141::class, 'ROOT', 'urn:example:test'],
            'rfc2045' => ['rfc2045', Library\Rfc2045::class, 'ROOT', 'text/plain'],
            'rfc2046' => ['rfc2046', Library\Rfc2046::class, 'ROOT', 'text/plain'],
            'rfc4408' => ['rfc4408', Library\Rfc4408::class, 'ROOT', 'v=spf1 -all'],
            'rfc5321' => ['rfc5321', Library\Rfc5321::class, 'ROOT', 'a@example.org'],
            'rfc5322' => ['rfc5322', Library\Rfc5322::class, 'ROOT', 'a@example.org'],
            'rfc6376' => ['rfc6376', Library\Rfc6376::class, 'ROOT', 'v=1; a=rsa-sha256; d=example.org; b=YWJj;'],
            'rfc6531' => ['rfc6531', Library\Rfc6531::class, 'ROOT', 'a@example.org'],
            'rfc6532' => ['rfc6532', Library\Rfc6532::class, 'ROOT', 'a@example.org'],
            'rfc6854' => ['rfc6854', Library\Rfc6854::class, 'ROOT', 'a@example.org'],
            'rfc7208' => ['rfc7208', Library\Rfc7208::class, 'ROOT', 'v=spf1 -all'],
            'rfc9989' => ['rfc9989', Library\Rfc9989::class, 'ROOT', 'v=DMARC1; p=reject'],
            'rfc9990' => ['rfc9990', Library\Rfc9990::class, 'ROOT', '<feedback xmlns="urn:ietf:params:xml:ns:dmarc-2.0">'
                . '<report_metadata/><policy_published/><record/></feedback>'],
            'rfc9991' => ['rfc9991', Library\Rfc9991::class, 'ROOT', 'Identity-Alignment: dkim, spf'],
            'rfc3339' => ['rfc3339', Library\Rfc3339::class, 'ROOT', '2024-01-02T03:04:05Z'],
            'rfc9557' => ['rfc9557', Library\Rfc9557::class, 'ROOT', '2024-01-02T03:04:05Z[UTC]'],
            'rfc9562' => ['rfc9562', Library\Rfc9562::class, 'ROOT', '550e8400-e29b-41d4-a716-446655440000'],
            'rfc5646' => ['rfc5646', Library\Rfc5646::class, 'ROOT', 'en-GB'],
            'rfc4648' => ['rfc4648', Library\Rfc4648::class, 'ROOT', 'YQ=='],
            'rfc6901' => ['rfc6901', Library\Rfc6901::class, 'ROOT', '/name'],
            'rfc8259' => ['rfc8259', Library\Rfc8259::class, 'ROOT', '{"a":1}'],
            'rfc9535' => ['rfc9535', Library\Rfc9535::class, 'ROOT', '$.name'],
            'rfc9651' => ['rfc9651', Library\Rfc9651::class, 'ROOT', '1, 2'],
            'rfc2234' => ['rfc2234', Library\Rfc2234::class, 'ROOT', 'r = "a"' . "\r" . '' . "\n" . ''],
            'rfc4234' => ['rfc4234', Library\Rfc4234::class, 'ROOT', 'r = "a"' . "\r" . '' . "\n" . ''],
            'rfc5234' => ['rfc5234', Library\Rfc5234::class, 'ROOT', 'r = "a"' . "\r" . '' . "\n" . ''],
            'rfc7405' => ['rfc7405', Library\Rfc7405::class, 'ROOT', 'r = %s"a"' . "\r" . '' . "\n" . ''],
        ];
    }
}
