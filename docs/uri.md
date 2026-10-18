# URI grammar and lexical rules

`Rfc3986` recognizes generic URI syntax, including non-HTTP schemes. It does not
check DNS, connectivity, scheme-specific port ranges or application URL policy.
The syntax follows [RFC 3986](https://www.rfc-editor.org/info/rfc3986/), particularly
Sections 2.1, 3.1–3.3 and 6.2.2.1. ABNF hexadecimal digits accept both cases under
[RFC 5234's core rules](https://www.rfc-editor.org/info/rfc5234/).

Run `php docs/examples/uri.php` for checked URI examples.

The grammar accepts compressed/uncompressed IPv6 and IPv4-embedded IPv6, lowercase
percent escapes, and either case of the IPvFuture `v` marker. It preserves the
input spelling; recognition does not normalize host names or escaped bytes.
Malformed escapes and addresses still fail complete-input parsing. IPv4-looking
registered names such as `1.2.3.256` remain valid generic host names; the host
chooses the IPv4 interpretation only when the address consumes the whole host.
An empty port is allowed by the generic syntax even if an application rejects it.
IPv6 zone identifiers are outside this RFC's syntax.

`unreserved` uses one character set, and `scheme`, `h16` and `dec_octet` use lexical
Regex terminals instead of character-level combinators. Their original combinator
definitions remain in comments beside the executable rules in
[Rfc3986.php](https://github.com/vanderlee/Comprehend/blob/master/extensions/Network/Grammar/Rfc3986.php). The original IPv6, host, IPvFuture and
standalone path rules are also preserved there to document the corrected choices.
The comments are historical implementations, not an alternative runtime grammar.

Named URI rule boundaries and overrides remain available. Lexical rule interiors
have fewer CST/AST descendants: for example, `scheme` no longer contains an ABNF
`ALPHA` node for each letter. Raw source spans and bytes remain exact. The lexical
spellings are atomic rather than accepting automatic spacing between their
characters. Parser-entry, work and depth counts can change with the graph, so
application budgets should be checked against the grammar being used. Regex
terminals follow the normal complete-subject and session-cache policies described
in [file input](file-input.md#regex-boundaries).

The standalone `path` rule selects the longest matching path form so a nullable
`path_abempty` cannot hide a nonempty rootless path. Within IPv6, a repeated
`h16 ":"` prefix stops before `::`, leaving the separator to its owning branch.
These are grammar changes on the existing recognition runtime.
