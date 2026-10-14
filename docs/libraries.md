# Bundled RFC grammars

Rfc2234, Rfc4234, Rfc5234 and Rfc7405 provide ABNF-related reusable rules.
Rfc3513, Rfc3986 and Rfc4408 provide IPv6, URI and SPF-related grammar assets.
They are typed object grammars using explicit Ruleset references and the same
source runtime as application parsers.

```php
$rfc = new \Vanderlee\Comprehend\Library\Rfc3986();
$result = $rfc->ref('IPv4address')->parseAll(
    new \Vanderlee\Comprehend\Source\ByteSource('192.0.2.1')
);
```

Use explicit `ref('name')` imports; magic property/method imports are removed.
Octet rules such as OCTET require ByteSource. ASCII rules generally work in both
source modes; mode-incompatible octet bounds are explicit errors in UTF-8 mode.
Tests preserve existing acceptance fixtures in both modes where meaningful.
These assets are not a claim of exhaustive RFC conformance; conformance review
is separate from runtime consolidation. Constructors accept explicit Parser,
Definition or factory overrides as named maps.

The [ABNF IPv4 example](examples/abnf.php) compares the frontend's documented
local-longest policy with equivalent object/Ruleset grammars and bundled RFC
acceptance fixtures. It does not promise generalized ABNF ambiguity handling.
