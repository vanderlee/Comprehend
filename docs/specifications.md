# Specification grammar catalog

Classes live in `Vanderlee\Comprehend\Library`. Instantiate a class and call
`freeze()->parse($input)` for its default root. Use `rule('name')` to import a
component or `ref('name')` when a low-level Parser is required. Names use
underscores; constructors accept named override maps. These grammars use the
existing recognition session, source spans, CST/AST and explicit commit behavior.

This catalog implements the current specifications from the proposed high,
medium and small RFC additions. Historical predecessors are documented as RFC
relationships, rather than supplied as aliases with potentially different syntax.

| Class | Default root | Other useful imports and coverage |
| --- | --- | --- |
| `Rfc5322` | `mailbox` | Modern email address syntax: `addr_spec`, `address`, `address_list`, `mailbox_list`, `group`, `comment`, `cfws`, `quoted_string`, `msg_id`. Also lexical `fields`/`message` and individual `from`, `sender`, `to`, `subject` headers. Obsolete Section 4 forms are not included. |
| `Rfc6854` | `mailbox` | Updates `from` and `sender` to permit group addresses. |
| `Rfc6532` | `mailbox` | Extends email address/header lexical components with validated UTF-8 scalars. |
| `Rfc5321` | `mailbox` | SMTP envelope addresses, `path`, `reverse_path`, `forward_path`, `command`, `mail`, `rcpt`, `helo`, `ehlo`, `reply` and `reply_line`. Header comments and folding are not envelope address syntax. |
| `Rfc6531` | `mailbox` | SMTPUTF8 local parts and domain labels. IDNA conversion and extension negotiation are separate. |
| `Rfc3339` | `date_time` | `full_date`, `full_time`, `partial_time`, `time_offset`, and their components. Gregorian dates are checked, including year zero. Leap-second spelling is accepted without consulting historical announcements. |
| `Rfc9557` | `date_time_ext` | Time-zone and calendar annotations. Time-zone lookup, offset consistency and critical-annotation handling require application logic. |
| `Rfc8259` | `json_text` | `value`, `object`, `array`, `member`, `string`, `number`, `ws`. Duplicate members and number spelling are preserved. String syntax allows escaped unpaired surrogates as in the RFC ABNF; interoperability validation and decoding are separate. |
| `Rfc6901` | `json_pointer` | `reference_token`, `array_index`, `uri_fragment`; fragment decoding is checked against pointer syntax. Resolving a pointer against a document is separate. |
| `Rfc9535` | `jsonpath_query` | Segments, selectors, slices, filters and standard functions' argument/result categories. Does not execute queries. Extension functions require application-defined type information. |
| `Rfc9651` | `sf_list` | `sf_item`, `sf_dictionary`, `inner_list`, `parameters`, scalar types. Numeric limits and display-string UTF-8 are checked. Duplicate-key interpretation and value construction are separate. |
| `Rfc1035` | `domain_name` | Escaped DNS text labels, `zone_record`, `zone_file`, `character_string`, `ttl`, `class`, `type`. Known single-line A/AAAA, name, MX, SOA and TXT records have typed data; folded/unknown records retain generic data atoms. Includes `$ORIGIN`, `$INCLUDE` syntax and the later `$TTL` directive. Never reads included files or wire packets. |
| `Rfc1034` | `domain_name` | Uses companion RFC 1035's text rules. Does not implement DNS resolution algorithms. |
| `Rfc1123` | `hostname` | ASCII hostname `label` and total-length limits. Distinct from unrestricted DNS presentation labels. |
| `Rfc9989` | `dmarc_record` | Extensible DMARC tag syntax, `dmarc_request`, alignment/PSD values and `reporting_uri`. Mandatory tags, duplicate-tag policy and DNS discovery are separate validation/evaluation. |
| `Rfc9990` | `aggregate_report` | XML syntax, feedback root/namespace and ordered top-level sections; `report_filename`. Full XSD validation, report semantics and compression are not included. |
| `Rfc9991` | `id_align` | The RFC's new Identity-Alignment field: `alignment_value`, `dmarc_method`, `identity_alignment_header`, `auth_failure`. The enclosing ARF report is not implemented. |
| `Rfc2045` | `content_type` | MIME `token`, `parameter`, `value`, `mime_version`, transfer-encoding names and lexical quoted-printable/Base64 bodies. Does not decode bodies or enforce every MIME line-length constraint. |
| `Rfc2046` | `content_type` | `boundary`, `multipart_body($boundary)`. Recognizes multipart framing with the supplied boundary; recursively interpreting part headers/bodies is separate. |
| `Rfc6376` | `tag_list` | DKIM tag lists, `signature`, `key_record`, `signature_header`. Rejects duplicate tag names. Required-tag/value constraints, algorithms and signature verification are separate. |
| `Rfc3987` | `IRI` | Extends URI rules with `ucschar`, `iprivate` and `irelative_ref`. URI mapping, normalization and IDNA conversion are separate. |
| `Rfc6570` | `uri_template` | All four template levels: `expression`, `varspec`, `varname`, `pct_encoded`. Does not expand variables. |
| `Rfc8141` | `urn` | `nid`, `nss`, resolution/query components and fragments. Namespace registration and namespace-specific semantics are separate. |
| `Rfc9562` | `uuid` | `uuid_urn`. Generic UUID spelling, including Nil/Max bit patterns. Does not generate identifiers or validate version-specific meaning. |
| `Rfc4648` | `base64` | `base16`, `base32`, `base32hex`, `base64url`, explicit unpadded Base64 variants. Canonical zero pad bits are checked; no whitespace is inserted or ignored. |
| `Rfc5646` | `language_tag` | Language/script/region/variant/extension/private-use and grandfathered tag syntax. Duplicate variants and extension singletons are rejected; registry membership and canonicalization are separate. |
| `SemVer` | `version` | SemVer 2.0.0 numeric, prerelease and build identifiers. Does not parse Composer/npm dependency ranges or compare versions. |
| `Xml` | `document` | XML 1.0 Fifth Edition document, element, attribute, reference, comment, CDATA, PI and internal DTD declaration syntax. Checks matching tags, unique attributes, legal characters, document-type root and undeclared entity names where no external subset is present. UTF-8 input only; no external resource loading, entity expansion, DTD validation or namespace processing. |
| `Yaml` | `stream` | YAML 1.2.2 streams, directives, block/flow collections, explicit keys, anchors, aliases, tags and scalar styles. Preserves spelling and indentation; does not resolve scalar types, construct tagged values, bind aliases or compare mapping keys. |

Every RFC class links to its RFC text in the class PHPDoc. Where an update or
replacement is present in the library, the PHPDoc also links to that class.
Non-RFC classes link to a versioned official specification.

## Recognition and interpretation

These assets provide composable syntax rules, not network clients or complete
protocol implementations. A successful parse establishes the documented grammar
coverage. It does not establish mail deliverability, DNS existence, cryptographic
validity, XML schema validity, registry membership or successful query evaluation.

`Rfc5322::message` retains lexical headers and body text; header cardinality and
required fields are not checked by that lexical rule. Import the individual typed
header rules when their value syntax matters. `Rfc6854` changes those typed
originator rules without silently changing `Rfc5322`.

XML parsing never loads external entities or DTDs. YAML parsing never instantiates
objects from tags or expands aliases. This keeps recognition pure. Consumers can
attach cached semantic projections/validators and explicit commit effects through
the normal API.

YAML's indentation parser delegates flow expressions to the typed parser graph,
retains source-bound children, and enters nested blocks through `ParseSession`.
Depth/work limits therefore apply to block nesting as well as ordinary grammar
recursion. Inputs and output spans remain byte based in both source modes.

## Verification

`SpecificationsTest.php` covers accepted/rejected examples in byte and UTF-8
sources. `SpecificationLifecycleTest.php` covers overrides, frozen snapshots,
nonzero byte offsets, parsing limits, explicit effects and MIME boundaries.

`UpstreamSpecificationsTest.php` adds acceptance fixtures from:

- YAML Test Suite: 402 inputs, including variant cases.
- HTTP Working Group structured-field tests: 1598 inputs.
- JSONPath compliance suite: 715 selectors, including standard-function typing.

The fixture [provenance and licenses](../tests/Fixtures/Specifications/README.md)
pin upstream revisions. These checks cover syntax acceptance and source round
trips; they do not test value construction, serialization or query execution.
Passing these finite corpora is not a claim of exhaustive specification conformance.

Run `php docs/examples/specifications.php` for checked imports across the catalog.
