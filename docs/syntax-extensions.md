# Syntax extensions and automatic loading

`Syntax::extend(SyntaxExtension $extension)` installs named factories into a new
immutable vocabulary. Explicit registrations stay local, and existing vocabularies
and expressions remain unchanged. Extensions use `defineRule()` and `defineFluent()`
internally, preserving their naming, replacement and typed factory contracts.

Bundled extensions load automatically on first use, including static and fluent
calls. No registration is needed:

```php
use Vanderlee\Comprehend\Comprehend as C;

$name = C::asciiIdentifier()->map('strtoupper');
$values = C::collect($name)->parse('hello')->value(); // ['HELLO']
$syntax = C::syntax(); // also includes discoverable factories
```

`extend()` remains useful for configured providers and explicit overrides.

```php
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\AsciiIdentifiers;

$base = C::syntax();
$syntax = $base->extend(new AsciiIdentifiers());
$name = $syntax->asciiIdentifier();
$keyword = $syntax->asciiKeyword('let');
```

The original `$base` and global `C::asciiIdentifier()` can also discover this bundled factory.
Expressions constructed through `$syntax` retain that vocabulary, so extension
factories can also be chained like other static factories. A chained factory
appends its parser; it does not automatically select that parser's value. Use
`ignoreThen()` when that value is wanted. The [Extraction expansion](extraction.md)
also loads automatically.

## Composer discovery

Bundled extensions, installed Composer packages, and the root application can
advertise providers in `extensions/*.extension.json`. Each file declares one
provider; its filename does not need to match the provider class. For example,
`extensions/Identifiers.extension.json`:

```json
{
  "class": "Acme\\Parsing\\Identifiers",
  "rules": ["identifier"],
  "fluents": ["identifier"]
}
```

Only files directly in the package's `extensions/` directory are discovered.
Nested directories and other JSON files are ignored. Filenames are processed in
sorted order, and each resolved package directory is visited once. A package
without matching files needs no configuration. File discovery also works when an
installed package has no `composer.json`.

Existing declarations in `composer.json` remain supported:

```json
{
  "extra": {
    "comprehend": {
      "extensions": [
        {
          "class": "Acme\\Parsing\\Identifiers",
          "rules": ["identifier"],
          "fluents": ["identifier"]
        }
      ]
    }
  }
}
```

Each provider implements `SyntaxExtension`, is autoloadable, and has a public
constructor requiring no arguments. Supply both name lists, using `[]` when empty.
Providers must be stateless; use explicit `extend()` for application configuration.
Their returned rule and fluent names must exactly match the advertised names,
ignoring case and order. Ordinary explicit registration still permits duplicate
keys with later registrations replacing earlier ones.

Composer 2 installation data locates package roots; PHP files are not scanned.
Inline Composer declarations and discovered files are merged without source
priority. Identical declarations for the same provider within a package are
deduplicated; inconsistent declarations fail discovery. Bundled declarations live
beside their classes in the library's own `extensions/` directory and are discovered
even without Composer installation data. JSON is the only supported manifest format.

The index is built once per process when discovery is first needed. `Syntax::names()`
includes advertised names without constructing providers. `C::syntaxNames()` still
lists reserved built-in names. Bundled providers also work without Composer metadata.
Restart long-running processes after changing installed packages or metadata.

Built-ins take precedence, followed by explicit vocabulary registrations, then
discovered factories. Rule and fluent names are independent and case-insensitive.
If two providers advertise the same name and kind, calling it raises an error
identifying both packages and classes. An explicit `defineRule()`, `defineFluent()`,
or `extend()` registration for that kind resolves the conflict locally. A conflicting
fluent fails before rule-appending fallback. Identical repeated provider declarations
within one package are deduplicated; inconsistent declarations fail discovery.

Unreadable files, malformed JSON, and invalid declarations fail discovery with
package and manifest-path context. Files must contain a JSON object. Provider classes and
registrations are validated only when needed, including reserved-name checks.
Deferred provider-loading errors also identify the declaring manifest files.
Both registration iterables must succeed before any factories are cached; failures
publish no partial registrations. Recursive loading of a provider is rejected.
Successful registrations are cached, while factories run for every authoring call.
Loading never installs dependencies or performs network requests, and parsing never
triggers discovery, construction, or factory calls.

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
graphs without application effects. Explicit installation bypasses discovery;
both paths use the same typed parser graph and recognition runtime.

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
use Vanderlee\Comprehend\Extensions\Extraction;
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

$syntax = Extraction::syntax()->extend(new Delimiters());
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

The optional [Recovery extension](recovery.md) registers `recoverUntil()`,
`recoverDelimited()`, `recoverItem()` and `recoverWith()` as rules and fluents.

## Expression tables

The optional [Expressions extension](expressions.md) installs `operatorExpression()`
and value-preserving `expressionChoice()`. It supports explicit precedence,
left/right/non-associative infix operators, and prefix/postfix operators, using
the same parser graph and lazy lifecycle.
