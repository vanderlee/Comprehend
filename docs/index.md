# Comprehend

Comprehend is a PHP parser-combinator library for building grammars from small,
reusable parsers. It supports named grammars, ABNF authoring, RFC libraries,
immutable syntax trees and lazy semantic values with explicit commit effects.

## Installation

Requires PHP 8.2+, JSON and mbstring. DOM is optional for XML serialization.
Comprehend is MIT licensed.

```sh
composer require vanderlee/comprehend
```

See the [breaking-release guide](breaking-release.md) for the status and
migration requirements of the 2.0.0 redesign.

## Quick start

The following example activates the optional [Extraction expansion](extraction.md).

```php
require 'vendor/autoload.php';

use Vanderlee\Comprehend\Extensions\Extraction\Extraction;
$age = Extraction::syntax()->integer(0, 150)
    ->named('age')
    ->mapText(static fn(string $text): int => (int) $text);

$result = $age->parse('42');
if ($result->ok) {
    echo $result->output();  // 42 (int)
    echo $result->rawText(); // '42' (original text)
}
```

`parse()` requires complete input. Semantic values are lazy and cached;
`rawText()` preserves the original source. All offsets and spans use bytes.
Side effects run only through explicit `commit()`.

## Explore the documentation

- Start with the [tutorial](tutorial.md) and [grammar-authoring guide](grammar-authoring.md).
- Build domain objects with [Parse application values](application-values.md).
- Learn about [sources](sources.md), [spacing](trivia.md) and [diagnostics](diagnostics.md).
- Extract [semantic values](semantic-values.md) and navigate [syntax trees](ast.md).
- Consult the [API reference](reference.md), [ABNF subset](abnf.md) and [RFC libraries](libraries.md).
- Browse the [runnable PHP examples](https://github.com/vanderlee/Comprehend/tree/master/docs/examples).

Examples run locally with PHP; this website serves static documentation.
