# Optional Syntax extensions

`Syntax::extend(SyntaxExtension $extension)` installs named factories into a new
immutable vocabulary. Nothing is registered globally, and existing vocabularies
and expressions remain unchanged. Extensions use `define()` internally, so names,
replacement behavior, typed arguments and factory return contracts are identical.

```php
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Syntax\Extension\AsciiIdentifiers;

$base = C::syntax();
$syntax = $base->extend(new AsciiIdentifiers());
$name = $syntax->asciiIdentifier();
$keyword = $syntax->asciiKeyword('let');
```

The original `$base` and global `C::asciiIdentifier()` have no such factory.
Expressions constructed through `$syntax` retain that vocabulary, so extension
factories can also be chained like other static factories. A chained factory
appends its parser; it does not automatically select that parser's value. Use
`ignoreThen()` when that value is wanted.

## Writing an extension

Implement `Vanderlee\Comprehend\Syntax\SyntaxExtension`. Its one method returns
`iterable<string, callable>` and can use generators or arrays:

```php
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Syntax\SyntaxExtension;

final class Punctuation implements SyntaxExtension
{
    public function factories(): iterable
    {
        yield 'semicolon' => static fn(): C => C::text(';');
        yield 'comma' => static fn(): C => C::text(',');
    }
}

$syntax = C::syntax()->extend(new Punctuation());
```

Installation consumes the iterable immediately and in order. Factories themselves
run only when invoked during grammar authoring, not during installation or input
recognition. Factories may return Comprehend, Parser or Definition, and named and
default parameters keep their typed signatures. Extension factories should build
graphs without application effects. No loader, discovery, shared registry or
additional recognition runtime is involved.

Names are case-insensitive. Later registrations replace earlier custom factories
under the same name, including duplicate keys yielded by a generator. Built-in
and declared method names remain reserved; `extend` is now a reserved Syntax name.
An empty iterable returns the original vocabulary. If iteration or registration
throws, the original vocabulary is unchanged and no partially extended vocabulary
is returned. External side effects performed by extension code cannot be undone.

The minimal contract registers static factories only. Existing `defineFluent()`
remains available separately for transformations of the current expression.

## Bundled ASCII vocabulary

`AsciiIdentifiers` is optional and constructs only existing built-in parsers:

- `asciiIdentifier()` recognizes `[A-Za-z_][A-Za-z0-9_]*` greedily and returns the
  original text. Reserved words are not automatically excluded.
- `asciiKeyword(string $word)` validates the word against that same alphabet,
  recognizes it with Text, and rejects an immediately following `[A-Za-z0-9_]`.
  Invalid keyword arguments throw during authoring.

The keyword's `lexeme()` ensures its boundary probe runs before intervening spacing
is consumed. Identifier membership is explicitly case-sensitive, keeping the
alphabet ASCII even in Unicode/case-insensitive grammars. Keyword literal spelling
follows the existing Text case policy; under case-insensitive recognition Text's
normal Unicode folding still applies. Output always retains source spelling.

Both byte and validated UTF-8 sources work, with byte-based offsets. Membership
and boundaries remain ASCII: a non-ASCII character terminates an identifier, and
`asciiKeyword('if')->matchPrefix('ifé')` accepts `if`. Neither parser checks the
character preceding the requested start offset. Complete-input parsing still
rejects any remaining text. Unicode identifiers, normalization and automatic
reserved-word rejection are outside this vocabulary.

These helpers use general Regex, whose file-subject reads follow the existing
[regex limits and caching rules](file-input.md#regex-boundaries). This extension
does not promise bounded-window scans or eliminate full-subject materialization.
Values remain lazy, trees preserve syntax, and effects require explicit commit.

Run `php docs/examples/syntax-extensions.php` for checked examples.
