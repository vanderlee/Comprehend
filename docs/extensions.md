# Bundled extensions: overview and loading

Bundled extensions load automatically on first use, including static and fluent
calls. No registration is needed:

```php
use Vanderlee\Comprehend\Comprehend as C;

$name = C::asciiIdentifier()->map('strtoupper');
$values = C::collect($name)->parse('hello')->value(); // ['HELLO']
$syntax = C::syntax(); // also includes discoverable factories
```

`extend()` remains useful for configured providers and explicit overrides.

The bundled Network, Mail, DateTime, Uuid, SemVer, LanguageTag, Encodings, Json,
HttpStructuredFields, Xml, Yaml, and Abnf providers expose
[specification factories and explicit RFC versions](libraries.md). Their grammars
live in each subject's `Extensions\Name\Grammar` namespace. Each subject has its
own manifest and can be overridden independently in a local vocabulary.

`Extensions\Numbers\Numbers` supplies `integer()`, `binary()`, `octal()`,
`hexadecimal()`, `octet()` and `float()` through `Numbers.extension.json`. These factories
also append numeric parsers when chained, preserving ordinary sequence values.
Their parsers are `Extensions\Numbers\Parser\IntegerNumber` and `FloatNumber`.
See [Numbers](numbers.md) for float syntax, exponent options, native precision
and the shared `SignPolicy` enum. IntegerNumber defaults retain their existing behavior.

```php
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\AsciiIdentifiers\AsciiIdentifiers;

$base = C::syntax();
$syntax = $base->extend(new AsciiIdentifiers());
$name = $syntax->asciiIdentifier();
$keyword = $syntax->asciiKeyword('let');
```

The original `$base` and global `C::asciiIdentifier()` can also discover this bundled factory.
Expressions constructed through `$syntax` retain that vocabulary, so extension
factories can also be chained like other static factories. A chained factory
appends its parser; it does not automatically select that parser's value. Use
`ignoreThen()` when that value is wanted. The [Extraction expansion](extraction.md)
also loads automatically.

## Choose an extension

- [Numbers](numbers.md): integer representations, floating-point syntax and signs.
- [Extraction](extraction.md): application values, collections, mappings and folds.
- [Expressions](expressions.md): precedence tables and lazy operator reductions.
- [ASCII identifiers and keywords](ascii-identifiers.md): identifier tokens and keyword boundaries.
- [Specification grammars](libraries.md): subject factories and explicit RFC versions.
  Consult the [specification catalog](specifications.md) for coverage and
  [URI grammar](uri.md) for URI-specific behavior.
- [Error recovery](recovery.md): synchronization and retained invalid regions.
- [Reading and skipping runs](read-scanning.md): scanner factories supplied by the Optimizer extension.
- [ABNF authoring](abnf.md): compile grammar text into the shared parser graph.
  The compiler is autoloaded directly rather than discovered as a syntax factory.

## Extend the vocabulary

For application-specific factories and fluent operations, see
[Writing syntax extensions](syntax-extensions.md). To publish discoverable
providers or consume extension capabilities, see
[Discovery and capability providers](extension-discovery.md).
