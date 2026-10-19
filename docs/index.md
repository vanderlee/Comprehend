# Comprehend

![Comprehend logo](assets/images/logo.webp)

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

Parse `2d6+3` into a description of two six-sided dice with a +3 modifier.
The bundled Numbers and [Extraction](extraction.md) extensions load automatically;
this example parses the notation without rolling any dice.

```php
require 'vendor/autoload.php';

use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Numbers\SignPolicy;

$modifier = C::integerValue(-100, 100, allowLeadingZeros: true, sign: SignPolicy::REQUIRED)
    ->optionalValue(default: 0);
$roll = C::collect(
    C::integerValue(1, 100)->thenIgnore('d'),
    C::integerValue(2, 1000)->named('sides'),
    $modifier,
)->map(static fn(array $parts): array => [
    'dice' => $parts[0],
    'sides' => $parts[1],
    'modifier' => $parts[2],
]);

$result = $roll->parse('2d6+3');
if ($result->ok) {
    $value = $result->output(); // ['dice' => 2, 'sides' => 6, 'modifier' => 3]
    echo $result->rawText();    // '2d6+3' (original text)
}
```

`thenIgnore('d')` keeps the count's value, `collect()` gathers the values, and
`map()` gives them descriptive keys. `1d20` defaults the modifier to zero;
`2d6-3` produces -3. Counts range from 1–100, sides from 2–1000, and modifiers
from -100 to 100. The count and lowercase `d` are required.
`allowLeadingZeros: true` permits signed zero and leading zeros in the modifier.

`parse()` requires complete input. Semantic values are lazy and cached;
`rawText()` preserves the original source. All offsets and spans use bytes.
Side effects run only through explicit `commit()`.

## Explore the documentation

- Start with the [tutorial](tutorial.md) and [grammar-authoring guide](grammar-authoring.md).
- Build domain objects with [Parse application values](application-values.md).
- Use grammars as Laravel validation rules with the separate [Laravel bridge](laravel.md).
- Learn about [sources](sources.md), [spacing](trivia.md) and [diagnostics](diagnostics.md).
- Extract [semantic values](semantic-values.md) and navigate [syntax trees](ast.md).
- Author grammars with the [immutable facade](facade.md), [Rulesets](builder.md) or [ABNF](abnf.md).
- Browse [bundled extensions](extensions.md) and the [specification catalog](specifications.md).
- Extend Comprehend with [syntax factories](syntax-extensions.md),
  [discoverable providers](extension-discovery.md) or [custom parsers](source-and-extension-contract.md).
- Consult the [API reference](reference.md) for supported contracts.
- Browse the [runnable PHP examples](https://github.com/vanderlee/Comprehend/tree/master/docs/examples).

Examples run locally with PHP; this website serves static documentation.
