# Optional Syntax extensions

`Syntax::extend(SyntaxExtension $extension)` installs named factories into a new
immutable vocabulary. Nothing is registered globally, and existing vocabularies
and expressions remain unchanged. Extensions use `defineRule()` and `defineFluent()`
internally, preserving their naming, replacement and typed factory contracts.

```php
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\AsciiIdentifiers;

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

Implement `Vanderlee\Comprehend\Syntax\SyntaxExtension`. Both `rules()` and
`fluents()` return `iterable<string, callable>` using generators or arrays.
Return an empty iterable for either kind of registration you do not need:

Extensions previously implementing `factories()` must rename it to `rules()`
and add `fluents()`, returning `[]` when no fluent operations are supplied.

```php
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Syntax\SyntaxExtension;

final class Punctuation implements SyntaxExtension
{
    public function rules(): iterable
    {
        yield 'semicolon' => static fn(): C => C::text(';');
        yield 'comma' => static fn(): C => C::text(',');
    }

    public function fluents(): iterable
    {
        return [];
    }
}

$syntax = C::syntax()->extend(new Punctuation());
```

Installation consumes `rules()` first, then `fluents()`, immediately and in order.
Factories run only when invoked during grammar authoring, not during installation or input
recognition. Factories may return Comprehend, Parser or Definition, and named and
default parameters keep their typed signatures. Extension factories should build
graphs without application effects. No loader, discovery, shared registry or
additional recognition runtime is involved.

Names are case-insensitive. Later registrations replace earlier custom factories
under the same name, including duplicate keys yielded by a generator. Built-in
and declared method names remain reserved; `extend` is now a reserved Syntax name.
Two empty iterables return the original vocabulary. If iteration or registration
throws, the original vocabulary is unchanged and no partially extended vocabulary
is returned. External side effects performed by extension code cannot be undone.

`rules()` supplies factories installed through `defineRule()`. `fluents()` supplies
transformations installed through `defineFluent()`. Each fluent callback receives
the current Comprehend expression before user arguments and runs only during
grammar authoring. Fluent-only extensions return an empty iterable from `rules()`.

```php
use Vanderlee\Comprehend\Syntax\SyntaxExtension;

final class Delimiters implements SyntaxExtension
{
    public function rules(): iterable
    {
        return [];
    }

    public function fluents(): iterable
    {
        yield 'parenthesized' => static fn(C $item): C => $item->between('(', ')');
    }
}

$syntax = C::syntax()->extend(new Delimiters());
$result = $syntax->text('hello')->parenthesized()->parse('(hello)');
```

Static and fluent names are independent: a static call starts an expression,
while a fluent call transforms the current expression when both share a name.
Duplicate fluent names replace earlier fluent registrations case-insensitively.
Reserved names and typed factory contracts follow `defineFluent()`. A failure in
either iterable leaves the original vocabulary unchanged. `defineFluent()` also
remains available for registering individual transformations.

## Bundled ASCII vocabulary

`Extensions\AsciiIdentifiers` lives in the top-level `extensions/` directory,
separate from the core `src/` tree. Composer autoloads the
`Vanderlee\Comprehend\Extensions` namespace from that directory. It is optional
and constructs only existing built-in parsers:

- `asciiIdentifier()` recognizes `[A-Za-z_][A-Za-z0-9_]*` greedily and returns the
  original text. Reserved words are not automatically excluded.
- `asciiKeyword(string $word)` validates the word against that same alphabet,
  recognizes it with Text, and rejects an immediately following `[A-Za-z0-9_]`.
  Invalid keyword arguments throw during authoring.

Both names also have explicit fluent registrations. They append the token to the
current expression, preserving the previous rule-fallback behavior and ordinary
sequence values. For example, `$syntax->text('@')->asciiKeyword('let')->asciiIdentifier()`
recognizes `@let name` with an appropriate spacing policy. Use `ignoreThen()` with
the standalone factory when you want only the appended token's value.

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

## Expression tables

The optional [Expressions extension](expressions.md) installs `operatorExpression()`
and value-preserving `expressionChoice()`. It supports explicit precedence,
left/right/non-associative infix operators, and prefix/postfix operators, using
the same parser graph and lazy lifecycle.
