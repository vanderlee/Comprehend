# Source coordinates and parser extensions

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
lifetime policies require their own explicit contract; file implementations are
Phase 4 work. The runnable [execution examples](examples/execution.php) include a
custom parser implementing this contract.
