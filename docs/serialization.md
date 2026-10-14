# Direct tree serialization

ConcreteNode and AstNode implement JsonSerializable and expose `toArray()`,
`toJson()` and `toXml()`. No Token projection, namespace-layout adapter or
coordinate conversion is involved.

Array/JSON nodes contain `name`, `group`, `start`, `end`, `text`, `textEncoding`
and `children`. CST nodes additionally contain `kind`, `trivia` and `semanticLeaf`.
Start/end are canonical bytes. Valid UTF-8 text is emitted directly with
`textEncoding: utf8`; otherwise it is base64 with `textEncoding: base64`.
This allows arbitrary byte-source data to round-trip through JSON. Names and
groups are grammar metadata, independent of semantic values. JSON errors throw.
Nonempty tags are emitted as `tags`. AST nodes additionally emit nonempty
`preamble`/`postamble` lists, whose fragments have start/end/text/textEncoding.

XML returns DOMDocument with fixed `<node>` elements, name/group attributes,
start/end attributes, and a `<text encoding="base64">` element. CST nodes also
include kind/trivia/semanticLeaf attributes. Child `<node>` elements preserve tree order.
Text is always base64, preserving arbitrary octets, NUL and XML control bytes.
Grammar names/groups are escaped attribute values, never XML element names or
namespace prefixes. Historical Token JSON/XML schemas are removed.
Nonempty tags use `<tags><tag>...</tag></tags>`. Surrounding AST fragments use
`<preamble>`/`<postamble>` containers with `<span start="..." end="...">` elements
and base64 text. Empty tag and surrounding fields are omitted from all serializations.

Raw source is independent of semantic mappings and collection processors.
Choose CST serialization for complete concrete syntax/trivia, or AST serialization
for named semantic structure. AST text still spans original punctuation/trivia.
