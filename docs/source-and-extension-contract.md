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

Implement the optional `Parser\FailureDiagnosticAtStart` marker interface when
a failed recognition should report its fallback diagnostic at the entry byte
offset, independently of observed progress. `Not` and `Except` implement this
marker. Other parsers default to entry offset plus observed failure progress.
The marker does not change the failure length or consumption, and does not replace
diagnostics explicitly recorded during recognition.

Fallback expectations use the parser's diagnostic name by default. Override
`diagnosticExpectation(): string` to provide a different expectation, or use
`Parser\Terminal\DiagnosticExpectationTrait` to return the parser's `__toString()`
description. Built-in terminals use this trait; their subclasses inherit that
behavior regardless of namespace. Namespace names do not classify parsers.
Named rule paths remain independent of expectation formatting.

`Parser::children(): array` returns a list of all direct parser dependencies.
The default returns an empty list for terminals. Composite extensions must override
it to expose operands and stored policies, including spacing parsers, even when
they are not leading recognition operands. Return the same parser identities on
repeated calls until the builder changes; sharing and recursive cycles are allowed.
Discovery must not recognize input or run semantic callbacks. Deferred references
return their resolved target, materializing grammar factories when necessary.

Graph analysis traverses this method rather than reflecting over parser fields.
Snapshot copying uses cloning and explicit dependency copying. Composite extensions
must also override `copyDependencies(GraphSnapshot)` to detach their owned operands
and policies. `children()` exposes graph edges; it does not assign copied edges
back into parser fields. Custom collection storage must be copied explicitly too.

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
seekable snapshots. The runnable [execution examples](https://github.com/vanderlee/Comprehend/blob/master/docs/examples/execution.php) include a
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
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

final class Wrapper extends Parser implements AnalyzableParser
{
    use ForwardingAnalysisTrait;

    public function __construct(private Parser $child) {}

    public function children(): array
    {
        return [$this->child];
    }

    protected function copyDependencies(GraphSnapshot $context): void
    {
        parent::copyDependencies($context);
        $this->child = $context->copy($this->child);
    }

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

Copy owned operands in `copyDependencies()` and expose them through `children()`
for discovery. Analysis operands must belong
to the collected graph; returning or looking up an undiscovered operand raises
`LogicException`. Discovery also includes
stored policy parsers, such as spacing, which need not be leading operands.

Subclasses inherit the analysis contract. If recognition behavior changes, update
the inherited analysis methods too. To opt out while extending a participating
built-in, return null from `analysisNullability()` and empty leading/progress
lists; this explicitly declares unknown behavior, without an opaque-parser warning.
Analysis must never run recognition, guards, semantic callbacks or commit effects.

## Snapshot copying

`Parser` and `Definition` implement `Builder\SnapshotParticipant`. The context owns
graph identity, shared copies and recursive traversal. `Parser::snapshot()` clones
the parser, registers the destination, calls `copyDependencies()` on the clone and
returns it. The base hook has no owned grammar dependencies; terminals inherit it.

Composite parsers override the protected `copyDependencies(GraphSnapshot): void`
hook, call the parent implementation first and replace every owned dependency with
its copied counterpart. Keep this in sync with `children()`, including stored
spacing policies. A wrapper uses `$this->child = $context->copy($this->child)`;
a container uses `array_map($context->copy(...), $this->parsers)`. Registration has
already happened when the hook runs, so recursive edges reuse the existing clone.

Owned Parser/Definition fields must be assignable on the clone. They remain
encapsulated by private/protected visibility; readonly scalar configuration may
remain readonly. Cloning preserves inherited metadata, and arrays use PHP's
copy-on-write behavior. Callback closures and external service/value objects stay
shared deliberately. Definition explicitly copies parser-valued generators;
factory callable dependencies stay caller-owned.

Cloning invokes custom `__clone()` hooks without invoking constructors. Clone
hooks must not recognize input, execute semantic callbacks or perform application
effects, and must not mutate original or shared dependencies. Use
`copyDependencies()` for graph copying rather than recursively
copying children inside `__clone()`, which runs before registration.

Register a detached destination **before** copying dependencies, then return that
same registered object. This closes cycles without duplicating nodes. Specialized
preparation may override `snapshot()` itself. For example, a custom Stub can
explicitly copy its bound operand:

```php
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Builder\SnapshotParticipant;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Stub;

final class SnapshotStub extends Stub implements SnapshotParticipant
{
    public function snapshot(GraphSnapshot $context): Parser
    {
        $copy = clone $this;
        $context->registerCopy($this, $copy);
        $child = $this->getParser();
        if ($child !== null) {
            $copy->setParser($context->copy($child));
        }

        return $copy;
    }
}
```

The context provides these operations:

- `copy(Parser): Parser` applies the parser's snapshot behavior, reusing
  previously registered destinations.
- `copyDefinition(Definition): Definition` copies a definition with the same
  identity tracking, including parser-valued generators and recursive dependencies.
- `copyValue(mixed): mixed` recursively copies Parser/Definition values and arrays,
  retaining other caller-owned values. Use it to copy the contents of custom storage.
- `registerCopy($original, $copy)` records a detached destination. Replacing an
  existing destination or changing the Parser/Definition category is an error.
- `registerIdentity($original, $candidate, $identity): Parser` registers a candidate
  or returns the destination already associated with that identity. Include a
  participant-specific namespace in the identity. Register before resolving its
  dependencies; if an existing destination is returned, reuse it immediately.
  Scalar/array identity components compare by value; objects and resources use
  identity without invoking object serialization.
- `resolvesReferences(): bool` distinguishes resolving freezes from non-resolving
  facade copies. A non-resolving copy must keep deferred lookup deferred.

The context rejects recursive re-entry before registration and participants that
return an unregistered destination. Discard the context if preparation throws.
Preparation may materialize grammar factories but must not recognize input, run
semantic callbacks or commit effects. Subclasses inherit snapshot behavior and
must maintain it when adding state requiring isolation.

There is no reflection-based copying or automatic discovery of fields to copy.
Existing custom composites that previously relied on reflected fields must add
`copyDependencies()`; returning those fields from `children()` alone is insufficient.

Built-in participants retain their distinct rules: RuleReference owns parameterized
identity and target extraction; Implementation resets cached targets before fresh
materialization; AbstractRuleset keeps only its freshly resolved root when freezing.

## Optional preparation reuse

Parsers may implement `Builder\ReusableSnapshot` and declare
`supportsSnapshotReuse(): bool`. Returning true permits the immutable facade to
cache a prepared FrozenGrammar, provided every copied participant qualifies.
This is preparation caching only: recognition results, semantic caches and commit
effects remain independent for each parse.

The capability must be pure and stable for the owned authoring copy. Preparation
must not depend on changing external state: this includes `snapshot()` factories,
deferred rule lookup and custom `freeze()` overrides. Composite parsers must copy
all owned dependencies through GraphSnapshot; each dependency, including spacing,
is checked independently. The original facade root is checked too, even when its
`freeze()` override returns a grammar composed entirely of capable built-ins.

Custom freeze overrides must accept
`freeze(int $flags = FreezeFlags::DEFAULT): FrozenGrammar` (with `Builder\FreezeFlags`
imported) and forward the flags when preparing the resulting grammar. Unknown
bits must be rejected, as in `FrozenGrammar::from($parser, $flags)`. Facade caches
are separate for each flag set; opting into reuse requires stable preparation
for each supported set.

Built-ins use `Builder\ExactClassSnapshotReuseTrait`, which declares
`return static::class === self::class;`. This deliberately excludes subclasses
despite interface inheritance. A safe custom subclass can use the trait again
to opt itself in without also opting in further subclasses. An explicit method
with the same exact-class check works too. The trait supplies the method; the
parser must still implement `ReusableSnapshot` (directly or through inheritance).
Custom parsers with no capability, or returning
false, continue to prepare freshly. No central registration is required. The
interface is a promise by the extension author; it cannot prove dependency ownership.

## Directive operands

`Directive\UnaryDirective` owns one parser operand and provides `getParser()`,
`children()`, detached snapshot copying and forwarding structural analysis. A
subclass implements recognition and its diagnostic string. Scoped policies must
still be restored with `try/finally`. Directives with additional parser dependencies
must expose them in `children()` and copy them in `copyDependencies()`, calling the
parent implementation first; `Space` demonstrates this for its spacer.
