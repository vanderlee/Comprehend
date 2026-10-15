# Source coordinates and parser extensions

Comprehend is the public authoring API. Custom recognizers still extend Parser
and implement the typed contract below; wrap them with `Comprehend::of($parser)`
to compose, register or parse through the facade. No manual parser export is
needed in Ruleset/Definition factories, which may return facade values directly.
Low-level constructors and extension code can still use detached `parser()`
exports; those objects remain mutable and are not facade expressions.

One typed contract serves every built-in and custom parser. There is no source
opt-in, class allowlist, legacy offset fallback or mutable string input hook.
Recognition uses byte positions on a stable Source and one per-run ParseSession.
See the [closed removal inventory](canonical-runtime.md).

```php
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

final class OneUnit extends Parser
{
    protected function recognize(Source $source, int $offset, ParseSession $session): AbstractMatch
    {
        $unit = $source->readUnit($offset);
        return $unit === null
            ? $this->failure($source, $offset)
            : $this->success($source, $offset, $unit->byteLength());
    }
    public function __toString(): string { return 'one unit'; }
}
```

Use `success(Source, int $offset, int $byteLength = 0, array|Success $children = [])`
and `failure(Source, int $offset, int $observedProgress = 0)`. Delegate through
`$child->recognizeInSession($source, $offset, $session)`; do not create a new
session for child recognition. This checked path validates source identity,
entry/end bounds, UTF-8 boundaries, diagnostics, guards and immutable CST output.
It never commits effects. A returned partial code point or foreign-source record
is an extension error. Internal execution records are not consumer result types.

Recognition must be pure. Attach `guard(callable(ConcreteNode): bool)` for
speculative rejection, `validateSemantic(callable(ParseResult): bool|string)`
for selected validation, `mapValue(callable(ParseResult): mixed)` for lazy typed
meaning and `onCommit(callable(ParseResult): void)` for effects. Restore every
session scope in `finally`, including trivia, case, preference and lexeme scopes.
Reading a preference after popping every preference scope throws `LogicException`.
Popping an empty case-sensitivity scope also throws `LogicException`.
Nullable repeated success throws at runtime; custom parsers must report real
byte consumption rather than character counts.

For `éa`, the byte positions are 0, 2 and 3. A match of `é` consumes two bytes;
an empty match at EOF has `[3,3)`. A failure at the start has zero consumed bytes
while its diagnostic can report the observed mismatch at byte 2. Use explicit
Utf8Source conversions when displaying code-point positions. Byte sources treat
each octet as a unit and never decode it.

Custom Source implementations supply byte slices/spans, recognition-unit reads
and boundary checks directly. UTF-8 mode must guarantee validated complete code
points and byte mode must preserve every octet. Spans must bind the same source
object, including custom forwarding implementations. Mutable/file resource
lifetime policies follow the [file-input contract](file-input.md) for built-in
seekable snapshots. The runnable [execution examples](examples/execution.php) include a
custom parser implementing this contract.

Custom recognizers with long loops should use session `readSourceUnit()` or
`consumeWork()` to cooperate with optional ParseLimits. Parser entry/depth checks
are automatic; arbitrary user PHP work cannot be preempted.

## Optional structural analysis

Custom parsers may additionally implement `Builder\AnalyzableParser` to participate
in freeze-time safety checks. No central parser registration is needed. Parsers
without this contract remain opaque, appear in `FrozenGrammar::analysisWarnings`,
and retain runtime recursion and repetition-progress guards.

The contract describes conservative structure, independently of recognition:

- `analysisNullability(NullabilityLookup): ?bool` returns false for non-nullable
  structure, true for potentially empty matches, or null when unknown. It does
  not promise that a match succeeds on any particular input. Use `get($child)`,
  `every($children)` for sequence consumption, or `any($children)` for alternative
  or shortest-operand consumption.
- `analysisLeadingParsers(NullabilityLookup): array` returns operands that may be
  called before consuming input. A sequence includes its nullable prefix and the
  next operand. An unknown prefix stops traversal, preserving conservative
  analysis; runtime guards remain necessary.
- `analysisProgressParsers(): array` returns operands whose successful matches
  must consume input. Repetition declares its body unless its maximum is zero.
  A known nullable operand fails freezing with a `Nullable repetition` diagnostic.
- `analysisError(): ?string` returns a local structural error or null. The analyzer
  appends the parser path; unresolved Stubs use this to reject missing targets.

All analysis methods must be pure and deterministic. Nullability is calculated
to a fixed point, starting at false for participating parsers and null for opaque
ones. Calculations must be monotone in the order **false < null < true** as child
estimates increase. Each iteration reads a stable set of estimates. A backwards
transition raises `LogicException` rather than allowing analysis to oscillate.
The lookup is valid only for that analysis step and must not be retained.

`DefaultAnalysisTrait` supplies empty leading/progress lists and no local error;
the parser still implements its nullability calculation. For transparent wrappers,
`ForwardingAnalysisTrait` supplies all four methods using an
`analysisTarget(): Parser` method:

```php
use Vanderlee\Comprehend\Builder\AnalyzableParser;
use Vanderlee\Comprehend\Builder\ForwardingAnalysisTrait;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

final class Wrapper extends Parser implements AnalyzableParser
{
    use ForwardingAnalysisTrait;

    public function __construct(private Parser $child) {}

    protected function analysisTarget(): Parser
    {
        return $this->child;
    }

    public function __toString(): string
    {
        return 'wrapper';
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        return $this->child->recognizeInSession($source, $byteOffset, $session);
    }
}
```

Keep operands in declared Parser fields or arrays so graph snapshots and discovery
include them. Analysis operands must belong to the collected graph; returning or
looking up an undiscovered operand raises `LogicException`. Discovery also includes
stored policy parsers, such as spacing, which need not be leading operands.

Subclasses inherit the analysis contract. If recognition behavior changes, update
the inherited analysis methods too. To opt out while extending a participating
built-in, return null from `analysisNullability()` and empty leading/progress
lists; this explicitly declares unknown behavior, without an opaque-parser warning.
Analysis must never run recognition, guards, semantic callbacks or commit effects.
