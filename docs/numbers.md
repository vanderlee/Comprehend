# Numbers extension

`Extensions\Numbers` loads automatically through `Numbers.extension.json` and
provides `integer()`, `binary()`, `octal()`, `hexadecimal()`, `octet()` and `float()`.
Static and scoped calls start expressions; chained calls append numeric parsers.
These factories return matched text, preserving its exact spelling and byte spans.
Their typed counterparts return PHP numeric values through a lazy, cached projection.

| Text factory | Typed factory | Value type |
|---|---|---|
| `integer()` | `integerValue()` | `int` |
| `binary()` | `binaryValue()` | `int` |
| `octal()` | `octalValue()` | `int` |
| `hexadecimal()` | `hexadecimalValue()` | `int` |
| `octet()` | `octetValue()` | `int` |
| `float()` | `floatValue()` | `float` |

Typed factories accept the same options, including `strict: false`. They use the
value already validated during recognition, so custom alphabets, case scopes and
integer limits do not require a second conversion. `rawText()` and syntax-tree
spans retain the original spelling. Native Float rounding and underflow still apply.
Chained typed factories append parsers and retain ordinary sequence semantics;
use a standalone typed factory or explicit value selection when its value is wanted.

```php
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Numbers\SignPolicy as S;

$number = C::float(minimum: null, sign: S::OPTIONAL);
$number->parse('+1.25e-2')->value(); // '+1.25e-2'
$number->map('floatval')->parse('+1.25e-2')->value(); // 0.0125
C::integer(sign: S::REQUIRED)->parse('+12')->value(); // '+12'
C::hexadecimalValue()->parse('ff')->value(); // 255
C::floatValue()->parse('1.25e2')->value();   // 125.0
```

Direct parsers are `Extensions\Numbers\Parser\IntegerNumber` and
`Extensions\Numbers\Parser\FloatNumber`. PHP reserves `Float` as a class name.
Direct typed parsers are `IntegerValue` and `FloatValue` in the same namespace;
they inherit the corresponding text parser's constructor options.
The former `Extensions\Integers` provider and parser namespace were renamed to
Numbers without compatibility aliases. Existing integer factory arguments remain
valid; the default `sign` policy preserves the previous behavior, and `strict` is
the final optional argument.

The Integer parser class is now named `IntegerNumber`. Update direct class imports
and references to `IntegerNumber::DIGITS`; the `integer()` factory is unchanged.
The former `Numbers\Parser\Integer` class has no compatibility alias.

## Integer representations

`integer(minimum: 0, maximum: null, base: 10, allowLeadingZeros: false,
digits: IntegerNumber::DIGITS, sign: S::MINUS_ONLY, strict: false)` supports custom UTF-8 alphabets,
case policies, bounded values and PHP integer overflow checking. The standard
alphabet supports bases 2–36. Binary, octal and hexadecimal factories select bases
2, 8 and 16; octet selects decimal bounds 0–255. Their leading-zero and sign
options follow the same rules.

## Floating-point representations

`float(minimum: 0.0, maximum: null, allowLeadingZeros: false,
allowExponent: true, sign: S::MINUS_ONLY, allowWholeNumbers: true, strict: false)` accepts ASCII decimal forms such as
`12`, `12.5`, `.5`, `12.` and `1.2e-3`. Both `e` and `E` are accepted regardless
of case policy. Exponent digits may have leading zeros, and their optional `+`
or `-` sign is independent of the leading sign policy.

Set `allowExponent: false` for decimal notation alone. `matchPrefix('1e3')` then
recognizes `1`, while complete `parse('1e3')` fails on the remaining text. Without
leading-zero permission, `0.5` is valid but `00.5` is not a complete match.
No locale-specific separators, alternate bases, digit separators or textual
`INF`/`NaN` values are supported.

Set `allowWholeNumbers: false` to require a decimal point or an enabled exponent.
This rejects bare `12` while accepting `12.0`, `.5`, `12.` and `1e3`. It controls
spelling, not whether the represented value is mathematically a whole number.
With exponents disabled as well, a decimal point is required and `1e3` has no
matching prefix. Bounds fallback uses the same restriction:
`C::float(maximum: 2, allowWholeNumbers: false)->matchPrefix('12.5')` fails rather
than returning `1`. The default remains true for existing grammars.

Range checking uses native PHP floats and their usual precision. Bounds must be
finite or null and ordered. Overflowing candidates are rejected; nonzero values
that underflow to native zero are permitted when the bounds include zero. This
does not change the returned source text or promise exact decimal arithmetic.

Both parsers try shorter complete numeric prefixes when a candidate is outside
the bounds or overflows. For example, `C::float(maximum: 2)->matchPrefix('12.5')`
recognizes `1`, while complete parsing fails. Shortened Float candidates must still
be complete numbers; a dangling exponent or sign is never consumed.

## Leading signs

| Policy | Accepted leading sign |
|---|---|
| `MINUS_ONLY` (default) | None or `-` |
| `OPTIONAL` | None, `+`, or `-` |
| `REQUIRED` | `+` or `-` |
| `FORBIDDEN` | None |

Signs control spelling independently of bounds. Negative numbers need a lower
bound that permits them, such as `minimum: null`.

Signed zero requires `allowLeadingZeros: true` for every policy that allows the
sign. This includes `+0`, `-0`, `-0.0` and `-.0e3`; `-0.5` has a nonzero
significand and is unaffected. A tiny nonzero significand that underflows is also
unaffected. With leading zeros disabled, REQUIRED consequently rejects zero.

Integer alphabets always exclude `-`. They also exclude `+` under OPTIONAL and
REQUIRED to avoid ambiguity with the leading sign. Under MINUS_ONLY or FORBIDDEN,
`+` may still be an explicitly configured digit.

## Optional strict matching

Append `strict: true` to a text or typed numeric factory to reject an invalid
numeric candidate instead of retrying shorter prefixes. The default is false,
preserving existing grammars. Strict matching does not require end-of-input:

```php
C::integer(maximum: 255)->matchPrefix('256')->value(); // '25'
C::integer(maximum: 255, strict: true)->matchPrefix('256')->success; // false
C::integerValue(strict: true)->matchPrefix('12kg')->value(); // 12
```

Integer candidates include the active digit alphabet and ASCII digits. Strict
binary `102`, octal `178`, and forbidden leading zeros fail. Characters outside
that candidate remain boundaries, so integer `12.5` can still match `12`.

Float treats a following ASCII digit, decimal point, or `e`/`E` as numeric
continuation. Strict `01.5`, `1e+`, `1.2.3`, and overflow fail. With exponents
disabled, strict `1e3` also fails. An `e`/`E` suffix such as `12em` is consequently
not an unrelated unit boundary. Bounds and all sign/whole-number options still
apply, and no shorter candidate is tried. Native underflow remains permitted.

Other suffixes and delimiters remain available to the enclosing grammar:
`12kg`, `12,`, and `12+3` can match the numeric prefix. Exponent signs belong to
the Float token; other signs can delimit a subsequent expression. Complete
`parse()` continues to reject any remaining text.

Recognition, bounds and guards remain pure. Mappings are lazy and cached, and
effects require explicit commit. Both parsers use the existing snapshot, source,
diagnostic and work-limit contracts. Float, like IntegerNumber, uses the session's shared
regex subject and may materialize a complete file subject.

Run `php docs/examples/numbers.php` for checked examples.
