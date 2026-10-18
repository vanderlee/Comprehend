# Bundled RFC and specification grammars

## Discoverable subject extensions

Grammar classes live in their owning extension's `Grammar` namespace, such as
`Vanderlee\Comprehend\Extensions\Network\Grammar\Rfc3986` and
`Vanderlee\Comprehend\Extensions\Encodings\Grammar\Rfc4648`.
Each subject below has its own bundled extension manifest. Factories load on first
static, scoped Syntax, or fluent use and construct a fresh specification graph.
They return the original text; they do not decode formats, resolve names, evaluate
policies, validate mail delivery, or construct application values.

Each grammar has one owner. Cross-subject imports remain explicit: Network uses
Abnf grammars, while Mail uses Abnf, Network, and Xml grammars. Shared base classes
live in core as `Builder\AbstractSpecification` and `Syntax\SpecificationExtension`,
with reusable Unicode helpers in `Extensions\Shared\Internal`. YAML-specific helpers live in
`Extensions\Yaml\Internal`. The former Library namespaces have no aliases.

```php
use Vanderlee\Comprehend\Comprehend as C;

C::ipv4()->parse('192.0.2.1')->value(); // '192.0.2.1'
C::text('@')->ignoreThen(C::uuid());
C::rfc3986(rule: 'host')->parse('example.org');
C::rfc5234(rule: 'DIGIT')->some()->parse('42');
```

| Extension | Factories |
| --- | --- |
| Network | `ipv4()`, `ipv6()`, `hostname()`, `uri()`, `uriReference()`, `absoluteUri()`, `iri()`, `urn()`, `uriTemplate()`, `hostnameLabel()`, `uriScheme()`, `uriAuthority()`, `uriHost()`, `uriPort()`, `uriRelativePart()`, `uriPathAbempty()`, `uriSegment()`, `uriQuery()`, `uriFragment()`, `uriPercentEncoded()`, `uriUnreserved()`, `iriUcschar()`, `iriPrivateChar()` |
| Mail | `emailAddress()`, `mailbox()`, `mailboxList()`, `emailAddressList()`, `mailMessage()`, `emailLocalPart()`, `emailDomain()`, `emailDotAtomText()`, `emailQuotedString()`, `emailFoldingWhitespace()`, `emailCommentsAndFoldingWhitespace()` |
| DateTime | `dateTime()`, `annotatedDateTime()`, `dateTimeDate()`, `dateTimeTime()` |
| Uuid | `uuid()`, `uuidUrn()` |
| SemVer | `semver()` |
| LanguageTag | `languageTag()` |
| Encodings | `base16()`, `base32()`, `base32Hex()`, `base64()`, `base64Url()`, `base64Unpadded()`, `base64UrlUnpadded()` |
| Json | `json()`, `jsonPointer()`, `jsonPath()`, `jsonString()`, `jsonNumber()` |
| HttpStructuredFields | `structuredFieldItem()`, `structuredFieldList()`, `structuredFieldDictionary()` |
| Xml | `xmlDocument()` |
| Yaml | `yaml()` |
| Abnf | `Compiler::compile()` (existing compiler API) |

`dateTime()` selects RFC 3339 `date_time`; `annotatedDateTime()` selects RFC
9557 `date_time_ext`, which also accepts timestamps without annotations.
`dateTimeDate()` and `dateTimeTime()` select `full_date` and `full_time`.
`emailAddress()` selects `addr_spec`; `mailbox()` may include a display name.
`emailAddressList()` selects `address_list` and may contain groups;
`mailboxList()` selects the narrower `mailbox_list`.

The URI subrule names select their corresponding RFC 3986 productions:
`uriPathAbempty()` is `path_abempty`, `uriPercentEncoded()` is `pct_encoded`,
and `uriUnreserved()` matches one unreserved unit. `hostnameLabel()` uses RFC
1123 `label`; the IRI character factories select RFC 3987 `ucschar` and
`iprivate`. Mail subrules select RFC 5322 `local_part`, `domain`,
`dot_atom_text`, `quoted_string`, `fws`, and `cfws`. `jsonString()` and
`jsonNumber()` select RFC 8259 lexical productions. Consumers that refine these
rules still need their own restrictions and semantic checks.

## Explicit RFC versions

Every implemented RFC has a factory accepting `string $rule = 'ROOT'`:

| Extension | Versioned factories |
| --- | --- |
| Network | `rfc1034()`, `rfc1035()`, `rfc1123()`, `rfc3513()`, `rfc3986()`, `rfc3987()`, `rfc4291()`, `rfc6570()`, `rfc8141()` |
| Mail | `rfc2045()`, `rfc2046()`, `rfc4408()`, `rfc5321()`, `rfc5322()`, `rfc6376()`, `rfc6531()`, `rfc6532()`, `rfc6854()`, `rfc7208()`, `rfc9989()`, `rfc9990()`, `rfc9991()` |
| DateTime | `rfc3339()`, `rfc9557()` |
| Uuid | `rfc9562()` |
| LanguageTag | `rfc5646()` |
| Encodings | `rfc4648()` |
| Json | `rfc6901()`, `rfc8259()`, `rfc9535()` |
| HttpStructuredFields | `rfc9651()` |
| Abnf | `rfc2234()`, `rfc4234()`, `rfc5234()`, `rfc7405()` |

The default selects the specification's existing `ROOT`. RFC 3986 has no
`ROOT` production, so its factory explicitly selects `URI` when the argument
is omitted or is `'ROOT'`. Other rule names are passed unchanged, including
existing underscores and case. Undefined rules retain the normal preparation error.
`ipv6()` selects a bare address; the RFC 4291 default root includes a prefix length.

Versioned factories never substitute a newer RFC, including when an older RFC
has been obsoleted. Existing shared implementations and coverage limits remain;
a versioned entry point is not a claim to implement all document requirements.
Direct classes still support custom rule overrides:

```php
use Vanderlee\Comprehend\Extensions\Network\Grammar\Rfc3986;
use Vanderlee\Comprehend\Extensions\SemVer\Grammar\SemVer;
use Vanderlee\Comprehend\Extensions\Xml\Grammar\Xml;
use Vanderlee\Comprehend\Extensions\Yaml\Grammar\Yaml;

$host = (new Rfc3986())->rule('host');
$prerelease = (new SemVer())->rule('prerelease');
$element = (new Xml())->rule('element');
$stream = (new Yaml())->rule('stream');
```

Factories are discoverable rather than reserved names. Explicit Syntax definitions
can override them locally. An explicit `extend(new Network())` (under
`Vanderlee\Comprehend\Extensions`) installs that subject into one vocabulary.
`FreezeFlags::NONE` remains supported. Existing Numbers, Extraction, Expressions,
AsciiIdentifiers, Optimizer, and Recovery factories are unchanged.

See [the runnable example](examples/library-extensions.php).


Rfc2234, Rfc4234, Rfc5234 and Rfc7405 provide ABNF-related reusable rules.
Rfc3513 and Rfc4291 provide IPv6 rules, Rfc3986 provides URI rules, and
Rfc4408 and Rfc7208 provide SPF-related grammar assets.
The library also includes email, MIME, DNS, JSON/JSONPath, HTTP structured fields,
timestamps, UUIDs, encodings, language tags, URI extensions, DMARC, SemVer, XML
and YAML. See the [specification catalog](specifications.md) for class names,
root rules, coverage boundaries and upstream acceptance tests.
They use the public facade with explicit Ruleset references and the same
source runtime as application parsers.

```php
$rfc = new \Vanderlee\Comprehend\Extensions\Network\Grammar\Rfc3986();
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
$spf = new \Vanderlee\Comprehend\Extensions\Mail\Grammar\Rfc7208();
$result = $spf->freeze()->parse('v=spf1 ip6:2001:db8::/32 -all');
$result->success; // true
```

Run `php docs/examples/libraries.php` for checked IPv6 and SPF examples.

The [ABNF IPv4 example](https://github.com/vanderlee/Comprehend/blob/master/docs/examples/abnf.php) compares the frontend's documented
local-longest policy with equivalent object/Ruleset grammars and bundled RFC
acceptance fixtures. It does not promise generalized ABNF ambiguity handling.
