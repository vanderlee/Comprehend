# ABNF authoring subset

The bundled ABNF extension exposes
`Extensions\Abnf\Compiler::compile($text, $root, $numericEncoding, $choice)`. It translates
authoring text into the existing Ruleset/combinator graph and returns a
FrozenGrammar. Syntax reading is a compile-time operation; generated parsers use
the same source, diagnostics, CST/AST, semantic and effect runtime as object
grammars. There is no generated PHP or separate input-recognition engine.

```php
use Vanderlee\Comprehend\Extensions\Abnf\Compiler;
use Vanderlee\Comprehend\Source\ByteSource;

$grammar = Compiler::compile('word = 1*DIGIT', 'word');
$result = $grammar->parseAll(new ByteSource('42'));
$result->success; // true
$result->rawText(); // '42'
$result->ast()->children[0]->name; // 'word'
```

`Compiler`, `Reader`, and `SyntaxException` live in `Extensions\Abnf` and are
autoloaded directly; no registration is needed. The extension preserves the static
compilation API and does not advertise syntax factories or capability providers.
Update former `Vanderlee\Comprehend\Abnf` imports; no compatibility aliases remain.

## Supported initial syntax

| Syntax | Translation |
| --- | --- |
| `name = expression`, references | Case-insensitive rule lookup, normalized lower-case names |
| `name =/ expression` | Incremental alternatives on an existing definition |
| `a b`, `a / b`, `(a b)` | Sequence, local Choice, grouping |
| `nitem`, `*item`, `n*mitem` | Exact, unbounded or bounded Repeat |
| `[expression]` | Optional Repeat |
| `"abc"`, `%i"abc"`, `%s"abc"` | Insensitive or explicitly sensitive ASCII literal |
| `%b...`, `%d...`, `%x...` | Numeric literal, dotted sequence or inclusive range |
| `; comment`, indented continuation | Grammar comments and folded expressions |

The subset follows the naming, literal and operator conventions of
[RFC 5234](https://www.rfc-editor.org/rfc/rfc5234.html), with explicit string case
flags from [RFC 7405](https://www.rfc-editor.org/rfc/rfc7405.html). Grammar text is
ASCII. Quoted-literal case folding stays within ASCII, including on UTF-8 input.
LF and CRLF are accepted, and the final newline is optional as a file authoring
convenience. Concatenation needs whitespace; quoted values have no
backslash escape syntax. Empty quoted strings produce zero-width nodes. Core
rules are available through the bundled RFC 5234 library, including DIGIT,
ALPHA, WSP, CRLF and OCTET. Explicit definitions can override their normalized
names; this does not rewrite dependencies inside the imported RFC library.

Numeric terminals default to octets (0–255). Pass `SourceMode::UTF8` to encode
numeric values as Unicode scalars; out-of-range values and surrogate endpoints
are rejected. Choose a matching input source: non-ASCII octets need ByteSource,
and Unicode numeric values need Utf8Source. ASCII grammars work in either source
mode. Imported OCTET remains the library's byte rule. Grammar whitespace is
syntax only: no whitespace skipping is inserted into the generated grammar.
Recognize input whitespace explicitly with rules such as WSP.

## Alternative semantics are explicit

ABNF declaratively describes alternatives; this runtime selects locally and
does not revisit a successful choice after a later suffix failure. Default
`choice` is `ChoicePolicy::FIRST`, with explicit LONGEST/SHORTEST enum cases available.
Pass a `ChoicePolicy` case; string policies are rejected.

```php
use Vanderlee\Comprehend\Runtime\ChoicePolicy;

$text = 'root = ("a" / "ab") "c"';
Compiler::compile($text, 'root')->parseAll(new ByteSource('abc'))->success; // false
Compiler::compile($text, 'root', choice: ChoicePolicy::LONGEST)
    ->parseAll(new ByteSource('abc'))->success; // true
```

The policy is scoped over all generated choices, including imported core
choices. Longest choice is not general CFG ambiguity handling or backtracking.
Repetition remains greedy. Refactor grammars when those execution semantics
differ from their declarative language; the frontend does not promise arbitrary
RFC grammar equivalence.

SyntaxException exposes a source-bound diagnostic and readable byte/line/column
position. Undefined names, duplicate definitions, invalid numeric/bound values,
unclosed groups and unsupported prose values fail explicitly. Generated graph
safety errors retain their existing LogicException rule paths. Left recursion
and nullable repeated children remain unsupported. Prose values, EBNF, grammar
text macros, generated PHP and generalized ambiguity handling are outside this
subset. Compilation has no ParseLimits; treat authoring text as trusted input.

## Checked RFC example

The [IPv4 example](https://github.com/vanderlee/Comprehend/blob/master/docs/examples/abnf.php) translates the IPv4/dec-octet definitions
from [RFC 3986 section 3.2.2](https://www.rfc-editor.org/rfc/rfc3986.html#section-3.2.2)
using explicit longest choice. It compares valid and invalid addresses with
object/Ruleset forms and the bundled RFC grammar, including raw output and spans.
Equivalent authored names also have matching AST shape; the bundled library
retains its own rule spellings/groups. Run `php docs/examples/abnf.php`; CI runs it.
