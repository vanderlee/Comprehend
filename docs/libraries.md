# Bundled RFC grammars

Rfc2234, Rfc4234, Rfc5234 and Rfc7405 provide ABNF-related reusable rules.
Rfc3513 and Rfc4291 provide IPv6 rules, Rfc3986 provides URI rules, and
Rfc4408 and Rfc7208 provide SPF-related grammar assets.
They use the public facade with explicit Ruleset references and the same
source runtime as application parsers.

```php
$rfc = new \Vanderlee\Comprehend\Library\Rfc3986();
$result = $rfc->rule('IPv4address')->parse('192.0.2.1');
$result->ast()->firstNamed('dec_octet')->rawText(); // 192
```

Use `rule('name')` for fluent imports; `ref('name')` is the low-level Parser
reference. Magic property/method imports are removed.
Octet rules such as OCTET require ByteSource. ASCII rules generally work in both
source modes; mode-incompatible octet bounds are explicit errors in UTF-8 mode.
Tests preserve existing acceptance fixtures in both modes where meaningful.
These assets are not a claim of exhaustive RFC conformance; conformance review
is separate from runtime consolidation. Constructors accept facade, custom Parser,
Definition or factory overrides as named maps. Factories may return facade values.

`Rfc3986::rule('URI')` accepts authority, absolute-path, rootless and empty-path
forms, including `https://example.com/path`, `file:/path`, `urn:example:animal`
and `scheme:`. Use `relative_ref` for relative paths, query-only and
fragment-only references. These alternatives follow
[RFC 3986 Appendix A](https://www.rfc-editor.org/rfc/rfc3986#appendix-A).

The `Rfc4408` root can be frozen and used to parse complete records such as
`v=spf1`, `v=spf1 -all` and `v=spf1 a mx -all`, with optional trailing spaces.
Its `explain_string` rule repeats individual macro expansions, literal characters
and spaces so every iteration consumes input, including when the explanation is
empty. This preserves the syntax of
[RFC 4408's macro productions](https://www.rfc-editor.org/rfc/rfc4408#section-8.1)
without repeating a nullable parser.

Use `Rfc4291` for the IPv6 text forms in
[RFC 4291](https://www.rfc-editor.org/rfc/rfc4291.txt), which supersedes RFC 3513.
Its root (also named `ipv6`) recognizes an address with a `/prefix-length` from
0 to 128. Import `ipv6_address` to recognize a bare address, including compressed
forms and embedded IPv4 addresses. Zone identifiers such as `%eth0` are outside
this RFC's address syntax.

Use `Rfc7208` for SPF record syntax in
[RFC 7208 Section 12](https://www.rfc-editor.org/rfc/rfc7208.txt), which supersedes
RFC 4408. Its root is `record`. It supports case-insensitive SPF keywords,
optional `ptr` domains, IPv4 and IPv6 CIDR bounds, and macro syntax including
`%{v}`. Named imports include `domain_spec`, `macro_string`, `explain_string`,
`ip4_network` and `ip6_network`; mechanism rules retain the existing names
`A`, `MX`, `PTR`, `IP4` and `IP6`. It recognizes syntax only: it does not perform
DNS lookups, evaluate policies, expand macros, or parse Received-SPF headers.
`Rfc3513` shares the corrected compressed-address recognition with `Rfc4291`,
including lowercase hexadecimal digits. `Rfc4408` also benefits from that IPv6
fix; its SPF-specific rules keep their existing behavior.

```php
$spf = new \Vanderlee\Comprehend\Library\Rfc7208();
$result = $spf->freeze()->parse('v=spf1 ip6:2001:db8::/32 -all');
$result->success; // true
```

Run `php docs/examples/libraries.php` for checked IPv6 and SPF examples.

The [ABNF IPv4 example](examples/abnf.php) compares the frontend's documented
local-longest policy with equivalent object/Ruleset grammars and bundled RFC
acceptance fixtures. It does not promise generalized ABNF ambiguity handling.
