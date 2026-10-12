# Source-based recognition

Phase 1.3 adds `Parser::matchSource(Source $source, int $byteOffset = 0)`.
It recognizes a prefix and returns the existing Success/Failure types, token
shapes and named results. Offsets, successful lengths, failure progress lengths,
token coordinates, and callback coordinates are **bytes**. `End` requires EOF.

This is an explicit new execution path. `match($string, $offset)` and
`__invoke()` keep legacy recognition and its documented multibyte limitation;
they do not infer a source mode or convert character offsets. ParseResult and
coordinate adapters are still phase 1.4 work. See the
[coordinate contract](source-and-extension-contract.md) and
[independent source API](sources.md).

Run `php docs/examples/execution.php` to check the source examples alongside the
legacy ASCII examples. The script reports legacy Unicode defects separately;
it checks that explicit UTF-8 recognition and extraction are correct.

```php
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Char;
use Vanderlee\Comprehend\Parser\Terminal\End;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;

$grammar = new Sequence(new Text("\u{00E9}a"), new End());
$grammar->matchSource(new Utf8Source("\u{00E9}b"))->match; // false
$grammar->matchSource(new Utf8Source("\u{00E9}a"))->length; // 3 bytes
$letter = (new Char("\u{00E9}"))->token('letter')->setResult('letter');
$result = $letter->matchSource(new Utf8Source("z\u{00E9}"), 1);
$result->token->text;                                  // é, complete UTF-8
$result->token->offset;                                // 1
$result->token->length;                                // 2
$result->getResult('letter');                          // é
(new Char(0xFF))->matchSource(new ByteSource("\xFF"));  // success, 1 byte
```

## Terminal mode rules

| Terminal | UTF-8 source | Byte source |
|---|---|---|
| Text | Validates its literal as UTF-8; compares code points and advances original byte boundaries | Matches literal octets |
| Char | One valid UTF-8 code point | One octet; a multibyte literal is a mode error |
| Any | One complete code point | One octet |
| Range | Bounds and input use Unicode scalar values, with explicit null bounds | Bounds and input use octet values |
| Set | Membership in original code points, including multibyte characters | Membership in literal octets |
| Regex | Adds PCRE `u`; verifies the captured byte offset equals the requested start | Octet regex; a supplied `u` modifier is a mode error |
| Integer | ASCII digits/sign and existing base/range semantics; lengths are bytes | Same ASCII numeric syntax |
| Start / End / Nothing | Zero-width success at the actual byte position; End compares source byte length | Same byte coordinate contract |

Integer arguments to Char and Range retain `chr()` octet construction. Values
above ASCII must use a ByteSource; use an actual Unicode string for a text bound.
Invalid text literals and incompatible mode choices throw
`InvalidArgumentException`, rather than becoming silent nonmatches.

Text, Char and Set case-insensitive matching compare explicitly UTF-8-lowercased
code points; they do not normalize input or consume according to the lowercased
string length. A comparison may lower one code point to several code points,
but still advances the original one-code-point span. This does not match one
input code point against multiple literal code points. Range rejects expanding
case mappings. Byte-mode casing changes ASCII letters only. Regex uses PCRE's
own case-insensitive rules. These policies do not promise full Unicode case
folding or grapheme matching.

UTF-8 regexes prohibit the byte escape `\C`; escaped or `\Q...\E`-quoted literal
backslashes followed by `C` are allowed. Byte mode also rejects leading PCRE
`(*UTF)`/`(*UTF8)` options. Captured matches must end on a code-point boundary. Regex
engine errors throw `RuntimeException`, distinct from an ordinary Failure.
Zero-length regex matches remain failures, preserving existing Regex behavior.
Matching uses the complete original subject with byte offsets; anchors such as
`^` retain their PCRE meaning. This API does not bound regex memory or stream.

## Composition, outputs and effects

Combinators, directives, Stub references, Rulesets and Definition implementations
propagate the source through Context. The existing choice policies, greedy
repetition, progress guard and exception-safe scope restoration remain in effect.

Spacing recognizes on the same source with independent default policy options,
then attaches only accepted effects before the next child. Failed branches and
failed repeat iterations discard speculative spacing effects. Callback text,
assignments, named result text and token text slice original byte spans; changing
case policy does not change raw output. Processed rules preserve their existing
output projection and selected effects. Definition validation may still evaluate
semantic result transforms speculatively; pure guards are phase 2 work.

Entry positions and returned progress must stay inside the source and, in text
mode, on code-point boundaries. Invalid entries throw `InvalidArgumentException`.
A custom parser splitting a code point or mutating input throws `LogicException`
before the root resolves effects. Failure lengths remain legacy diagnostic
progress, not committed consumption or structured farthest-failure diagnostics.

## Custom parser compatibility

Built-in parser classes are registered for source execution. Every custom
subclass, including one inheriting a built-in implementation, explicitly opts in:

```php
class CustomText extends Text
{
    protected function supportsSource(): bool
    {
        return true;
    }

    protected function parse(&$input, $offset, \Vanderlee\Comprehend\Core\Context $context)
    {
        return parent::parse($input, $offset, $context);
    }
}
```

The original protected `parse`, match-factory signatures and reference arguments
are unchanged. Opt-in declares byte lengths, valid source boundaries, unchanged
input, and deferred effects. Read `$context->source()` for the source/mode, and
`readSourceUnit($byteOffset)` for an octet or code-point span. Delegate to children
with protected `parseInContext(&$input, $offset, $context)` so compatibility and
boundary guards apply to them too. Reject unsupported modes explicitly.

Without opt-in, source execution throws `LogicException` identifying the custom
class. Legacy `match()` remains available for unmigrated extensions. The new
protected names `supportsSource` and `parseInContext` are extension points;
check downstream name/signature collisions when adopting this API.
`matchSourceUnresolved()` is an internal independent-context bridge for spacing;
ordinary callers use `matchSource()` to resolve the selected root.

Recognition currently supports in-memory ByteSource and Utf8Source. File sources,
ParseSession, canonical result spans and coordinate adapters remain separate
increments. Existing RFC fixture acceptance/lengths are exercised in both modes
where their ASCII paths are compatible; OCTET requires byte mode. This does not
certify every bundled RFC rule.
