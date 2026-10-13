# Legacy tokens over the concrete tree

Source-bound `token()` output now uses `Compatibility\TokenProjection` over the
immutable CST, rather than the selected semantic-match tree and parser token
callbacks. `TokenProjection::project($node)` also exposes the byte-coordinate
projection directly. Legacy string `match()` retains its existing callback path.

The CST snapshots legacy layout through `tokenTransparent` and
`tokenChildIndexes`. Transparent directives project their child's token; ordinary
nodes retain unnamed token containers. Automatic spacing is omitted from token
children, while explicitly parsed trivia preserves its old token layout.
Processed rules and `All` retain their old token-child suppression even though
their CST/AST recognition children remain available. Terminal naming flags still
hide token children. Naming mutations after recognition do not alter source
token output. Custom source-compatible parsers should describe naming and token
layout in the CST; legacy-only custom token callbacks remain on the string path.

The resulting objects are existing `Core\Token` instances. JSON keeps the fields
`group`, `name`, `text`, `offset`, `length`, `class` and `children`. XML continues
to use group namespace URIs, sanitized local names or parser classes, and leaf
text. Namespace declaration placement remains a DOM/libxml implementation
detail. Existing serializers operate over the projected tokens without changes.

Spans and raw text come from the original source, including UTF-8 and empty
nodes. Source-based empty tokens use their actual byte position; the legacy
string `Nothing` path still has its documented historical zero offset. Legacy
coordinate adapters convert projected token positions once and retain original
raw text in JSON/XML. Tokens retain their full input string for compatibility;
this does not provide bounded-memory file serialization.

AST and legacy token shape intentionally differ: AST promotes named descendants
and omits trivia, while tokens preserve the legacy containers and output rules.
Tests compare ASCII token arrays, JSON, XML and string output with the legacy
path and verify Unicode, namespaces, empty nodes and coordinate adapters.
