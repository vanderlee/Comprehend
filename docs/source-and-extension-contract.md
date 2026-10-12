# Source coordinates and extension compatibility

Phase 1.1 design contract, recorded 12 October 2026. This specifies the new
runtime and its compatibility adapters. Phase 1.2 implements the independent
[in-memory sources and spans](sources.md); the current parser remains unchanged.
The [current Unicode limitation](execution.md#current-unicode-limitation) still
applies. Terminal migration begins in 1.3 and result adapters in 1.4. Planned
parser/session/adapter contracts below are not yet available APIs.

## Canonical coordinates

All new recognition positions, consumed lengths, source spans, regex offsets,
and file seeks use **zero-based byte offsets into the original source**. Source
byte length is `strlen($input)` for an in-memory string. Encoding, case policy,
and output transformations never change this coordinate system or the source.

A span belongs to one source and is half-open: `[start, end)`. Its byte length
is `end - start`; raw text is the exact original bytes in that interval.
Require integer endpoints with `0 <= start <= end <= sourceByteLength`.
Reject invalid bounds with `InvalidArgumentException`; never clamp, round, or
wrap them. Endpoints from different sources cannot form one span, even if the
sources contain equal text. Source content must remain stable for a span's
lifetime.

An empty span is valid at any in-range byte position, including EOF. The empty
source has the valid span `[0, 0)`. A successful nullable parser returns
`[position, position)`, never an absent or inferred position. A successful
sequence includes the accepted intervening spacing in its span, even when that
spacing is omitted from legacy token children.

Text-mode recognition starts and ends on UTF-8 code-point boundaries. A generic
byte span or raw byte slice may split a code point; it remains an exact byte
operation and does not promise valid UTF-8. Encoding-aware reads and text parser
entry points reject such interior positions. Byte-mode recognition permits all
in-range byte boundaries.

New successful results record start/end and consumed byte length. Failed
recognition commits no input: its result span is `[start, start)` and consumed
length is zero. A separate farthest-failure byte position records diagnostic
progress; it is not the end of a successful span. Failure at EOF is a valid
position. Do not turn the old `Failure::length` into committed consumption.
Expected-symbol merging and diagnostic suppression are specified in 1.6.

## Separate character positions

A character position is a **zero-based count of Unicode code points** before a
UTF-8 boundary. It is separate from a byte offset and must be requested or
converted explicitly. Code points are not grapheme clusters, display columns,
UTF-16 code units, or user-perceived characters.

UTF-8 sources provide exact conversions in both directions between byte
boundaries and code-point positions. Position zero maps to byte zero; the
code-point count maps to byte EOF. Reject negative or out-of-range positions and
byte offsets inside a code point with `InvalidArgumentException`. Conversion
must not depend on ambient mbstring encoding. Byte sources have no implicit
character-position conversion; decode explicitly before requesting one.

### Contract examples

These are target coordinate examples, not results produced by today's defective
multibyte terminals. UTF-8 bytes are shown in hexadecimal; code-point positions
and lengths always have an explicit unit.

| Source | Bytes | Span | Raw text | Byte length | Code-point start/end |
|---|---|---|---|---|---|
| `abc` | `61 62 63` | `[1, 3)` | `bc` | 2 | 1 / 3 |
| `éa` | `c3 a9 61` | `[0, 2)` | `é` | 2 | 0 / 1 |
| `éa` | `c3 a9 61` | `[2, 3)` | `a` | 1 | 1 / 2 |
| `éa` | `c3 a9 61` | `[2, 2)` | empty | 0 | 1 / 1 |
| `éa` | `c3 a9 61` | `[3, 3)` | empty at EOF | 0 | 2 / 2 |
| empty | empty | `[0, 0)` | empty | 0 | 0 / 0 |
| `e` + U+0301 | `65 cc 81` | `[0, 3)` | decomposed `é` | 3 | 0 / 2 |
| `😀a` | `f0 9f 98 80 61` | `[4, 5)` | `a` | 1 | 1 / 2 |

For `éa`, code-point position 1 converts to byte offset 2. Byte offset 1 is
valid for a raw byte slice, but invalid as a UTF-8 read or parser position.
`[1, 2)` yields byte `a9`, not a complete character. The decomposed example
contains two code points; normalization must not collapse it to one. A UTF-8
match of `éa` spans `[0, 3)`; comparison with `éb` must fail rather than treating
two code points as two bytes.

## Encoding and input modes

The first sources are explicit in-memory **bytes** and **UTF-8 text** modes.
Do not auto-detect encoding from content or change semantics based on the input
class. Grammar/parser mode must agree with source mode; reject a mismatch rather
than silently treating an octet terminal as a Unicode terminal.

Byte mode accepts arbitrary bytes, including NUL and invalid UTF-8, and reads
one octet at a time. UTF-8 mode validates the complete initial in-memory source
at construction and rejects malformed or truncated input with
`InvalidArgumentException` before recognition. No replacement characters,
silent skipping, or implicit transcoding are allowed. The empty string is valid
in both modes.

UTF-8 mode reads one code point from a valid byte boundary and returns its bytes
and the next byte boundary. Reading at EOF returns no code point; it is distinct
from an empty successful parser match. Negative, beyond-EOF, and interior
positions are invalid arguments. A UTF-8 BOM remains source content (U+FEFF);
strip it only through an explicit grammar or preprocessing step. No Unicode
normalization or newline conversion occurs. Other encodings require an explicit
conversion before source construction, with positions referring to the converted
source; mapping them to the pre-conversion input is a separate feature.

Regex subject access and offsets use original bytes. Text-mode regex recognition
must use UTF-8-aware matching and preserve code-point boundaries; byte-mode
matching uses octet semantics. Pattern/source mode incompatibility must be an
explicit error. Regex errors are distinct from ordinary nonmatches. Exact
pattern option handling and anchoring belong to the terminal migration in 1.3.

Case-insensitive comparison may compute comparison values with explicit UTF-8
encoding, but must advance and slice using the original source boundaries.
It must not derive consumed byte lengths from a transformed string. Existing
ASCII case behavior remains a compatibility requirement; broader Unicode case
equivalence requires terminal-specific decisions and tests in 1.3.

Line/column indexing will be lazy in 1.6. Human-readable line/column positions
are separate from canonical offsets; that increment must specify indexing bases,
newline handling, and column units. They must never replace bytes in spans or
regex calls.

## Legacy coordinate policy

Today's runtime mixes units: `mb_strlen()` counts characters under ambient
encoding, `$input[$offset]` and `substr()` access bytes, and PCRE offsets are
bytes. The same old integer cannot reliably identify one multibyte position.
Neither guessing a unit nor converting previously returned broken tokens fixes
this. Old `Failure` also discards its starting offset.

The new legacy adapter will require one policy per parse, applied consistently
to supplied offsets, exposed token offsets/lengths, and callback offset/length
arguments:

| Adapter policy | Input position | Exposed position/length | Supported source |
|---|---|---|---|
| ASCII compatibility (default) | unchanged integer | unchanged integer; bytes and code points coincide | ASCII-only input |
| Explicit bytes | canonical byte offset | byte offset / byte length | byte or UTF-8 source; text positions must be boundaries |
| Explicit code points | convert code-point position to byte boundary before recognition | convert start/end back; length is end position minus start position | validated UTF-8 source only |

Default compatibility rejects non-ASCII input with `InvalidArgumentException`
instead of guessing old units. Explicit bytes permits arbitrary octets only with
a byte grammar/source. Explicit code points rejects byte sources and cannot
represent interior-byte spans. Example: matching `a` after `é` in `éa` requires
offset 2 under bytes or offset 1 under code points; outputs use that chosen policy
and length 1. Matching `é` has byte length 2 or code-point length 1.

Canonical spans stay byte-based under every adapter policy. Adapter token text
and callback text must come from canonical raw slicing, not `substr()` with
converted character positions. JSON/array offsets follow the chosen legacy
policy; new result/span output always states byte units. Adapter callers must
retain the policy with serialized legacy data, since the old shape has no unit
field. Never guess it when deserializing.

For failures, a legacy adapter may expose `length` as diagnostic distance from
the starting position to the farthest failure, converted using the selected
policy. This is explicitly diagnostic progress, not consumption; it cannot
reproduce every historical mixed-unit failure length. Reparse old multibyte
inputs with a declared policy rather than converting old results. Code-point
conversion must fail explicitly if a diagnostic byte position has no valid
text boundary.

These are future adapter rules. Phase 1.1 does not change `match()` or add an
ASCII-only restriction to the existing runtime. Keep legacy execution isolated
while adding sources in 1.2; do not silently activate a new offset policy during
terminal migration. Switch a grammar's recognition and extraction together or
keep it on the old path until its components are compatible.

## Subclass inventory

Inspected all Comprehend PHP files outside `vendor`, `build`, `.git`, `.idea`,
and the independent `-main` snapshot. External consumer subclasses are
unknown; absence from this checkout is not evidence that there are none.

| Family / locations | Existing subclass dependency | Migration exposure |
|---|---|---|
| Ten terminals in [Parser/Terminal](../src/Parser/Terminal) | All override `parse`; six use `CaseSensitiveTrait` | Byte indexing, mbstring lengths, regex offsets, `chr()`/`ord()` and result lengths must migrate together. `Start`, `End`, and `Nothing` need explicit zero-width positions. |
| [Sequence](../src/Parser/Structure/Sequence.php), [Choice](../src/Parser/Structure/Choice.php) through [IterableParser](../src/Parser/Structure/IterableParser.php) | `parse`, protected `$parsers`, argument conversion, mutable array access | Advancing positions must use bytes; retain current preference and spacing semantics. Freezing composition belongs to phase 3. |
| [Repeat](../src/Parser/Structure/Repeat.php), [All](../src/Parser/Structure/All.php), [Except](../src/Parser/Structure/Except.php), [Not](../src/Parser/Structure/Not.php) | `parse`, match factories, success/failure type checks | Progress, speculative spans, child effects and failure progress must stay distinct. A lookahead success is zero-width. |
| [CaseSensitive](../src/Directive/CaseSensitive.php), [Prefer](../src/Directive/Prefer.php), [Space](../src/Directive/Space.php) | `parse`, shared `Context`, nested child invocation | Preserve `try/finally` restoration and selected-path effects while adapting session state. |
| [Stub](../src/Parser/Stub.php) | `parse` delegates to a mutable parser reference | Carry the same source/session and byte position through references; do not resolve effects during delegation. |
| [AbstractRuleset](../src/Builder/AbstractRuleset.php), [Ruleset](../src/Builder/Ruleset.php), [Implementation](../src/Builder/Implementation.php) | `parse`, protected rule helpers/state, class-name and callable factories | Validators read local results during recognition; processors replace child output but retain effects. Preserve these contracts and factory subclass checks. |
| Seven [RFC Rulesets](../src/Library): Rfc2234, Rfc3513, Rfc3986, Rfc4234, Rfc4408, Rfc5234, Rfc7405 | Extend AbstractRuleset or another RFC; constructors and protected static `$name`; no custom `parse` overrides | ASCII conformance remains covered; numeric terminals/octet rules need explicit source mode, not inferred Unicode semantics. |
| [ContextScopeParser](../tests/src/Core/Context/ContextScopeTest.php) | Named custom Parser fixture overrides `parse`; exposes shared-context recognition | Keep the legacy protected signature and default `Context` path usable. |
| Anonymous Text subclasses in [RepeatTest](../tests/src/Parser/Structure/RepeatTest.php) and [SpacingEffectsTest](../tests/src/Core/Context/SpacingEffectsTest.php) | Four `parse` overrides: nullable success, deliberate exceptions, or `parent::parse()` | Signature changes would break subclass loading; throwing/nullable custom parsers remain supported by runtime guards. |
| Anonymous Context subclass in [ContextScopeTest](../tests/src/Core/Context/ContextScopeTest.php) | Overrides public `isCaseSensitive()` to probe/throw | Moving policy access into a session must not silently bypass custom Context behavior on the legacy path. |

There are no additional application-defined parser subclasses in this checkout.
The math examples compose objects and Rulesets rather than overriding `parse`.
`Success` and `Failure` extend `AbstractMatch`; no additional custom match or
Token subclasses were found. No parser subclass overrides `match()` or
`matchUnresolved()` here, but external subclasses may do so.

## Protected extension points and compatibility decisions

| Current extension point | Risk / decision before changing it |
|---|---|
| [Parser](../src/Parser/Parser.php) `parse(&$input, $offset, Context $context)` | Keep the legacy by-reference string argument, visibility, parameter order, Context type, and untyped return signature. Replacing it with Source/Session or adding mandatory type declarations can break overrides at class load time. Introduce the new runtime contract alongside it. |
| `success($input, $offset, $length = 0, &$successes = [])` | Keep signature and by-reference child argument on the legacy path. It captures raw input/offsets in lazy output closures; byte spans and source lifetime must replace those captures only through a reviewed adapter. |
| `makeMatch($success, &$input, $offset, $length = 0, &$successes = [])` and `failure(&$input, $offset, $length = 0)` | Preserve signatures. `makeMatch(false, ...)` currently drops its length argument, and `failure()` drops offset. Do not infer complete new diagnostics from those objects; define and verify the adapter's reconstruction limits. |
| Static `parseCharacter($character)` | Current integers use `chr()` and strings use ambient `mb_strlen()`. Preserve existing ASCII/octet constructor meanings on the legacy path; introduce explicit text/octet handling for the new runtime. |
| [ArgumentsTrait](../src/Core/ArgumentsTrait.php) static `getArgument`, `getArguments`, `getArgumentType` | Shared coercion and subclass/class-name factories depend on `Parser`. Do not bypass custom Parser objects or change array composition semantics while introducing Source. |
| [IterableParser](../src/Parser/Structure/IterableParser.php) protected `$parsers` | Subclasses can inspect/mutate children. Preserve the legacy container until phase 3 defines frozen graphs and adapters. |
| [AbstractRuleset](../src/Builder/AbstractRuleset.php) protected `$instanceRules`, static `$name`, and static `setRule`, `defineRule`, `isRuleDefined`, `undefineRule`, `call`; Ruleset overrides `call` | Keep signatures, reference parameters, inherited rule behavior and named token projection. Shared static state and cached roots are lifetime risks for frozen grammars, not reasons to change the coordinate contract silently. |
| [AbstractMatch](../src/Match/AbstractMatch.php) protected `$length` | Consumer subclasses may read/assign it. Preserve the legacy representation through adapters; canonical byte spans belong to new results, not a silent reinterpretation of this property. |

The scope helpers in SpacingTrait, PreferTrait, and CaseSensitiveTrait are
private, as are Token slicing and result/callback processing helpers. They are
not protected override points, but their behavior is observable through public
methods, closures, inherited terminals and custom Context objects.

Public integration risks also include `match()`/`__invoke()` prefix behavior,
phase-0 `matchUnresolved()` independent-context recognition, `skipSpacing()`'s
optional unresolved-match output, and `Success::addPrecedingMatch()` effect-only
matches. Preserve callback order and token/result shapes while migrating their
coordinates; keep speculative effects deferred. Definitions may evaluate result
transforms during validation, so pure guards and effect commit remain separate
phase-2 work rather than guarantees of this source contract.

New source implementations in 1.2 must be usable independently without requiring
legacy subclasses to implement new methods. At the runtime bridge, support old
subclasses on the ASCII compatibility path first. Multibyte or byte-mode custom
parsers require an explicit unit declaration and migration; reject undeclared
mixed-mode composition instead of guessing the units of a child match. Keep a
separate legacy execution path for extensions that cannot satisfy the new
contract yet. Any eventual removal or incompatible signature change requires
the documented major-release migration window.

## Review gate for the next increments

1. In 1.2, verify the examples above, invalid bounds, malformed UTF-8, EOF and
   interior-byte reads, explicit conversions, and independence from ambient
   encoding. Do not route old terminals through new sources yet.
2. In 1.3, verify each migrated terminal's byte lengths and extraction together,
   with existing ASCII/RFC examples, regex offsets, case policy, zero-width
   terminals and the `éa`/`éb` regression. Inventory external extension feedback
   before changing their integration path.
3. In 1.4, verify each declared adapter policy, token/callback coordinates,
   independent failure positions, and custom subclass loading and delegation.
   Never certify historical multibyte offsets as recoverable without reparsing.
