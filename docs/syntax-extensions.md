# Writing syntax extensions

`Syntax::extend(SyntaxExtension $extension)` installs named factories into a new
immutable vocabulary. Explicit registrations stay local, and existing vocabularies
and expressions remain unchanged. Extensions use `defineRule()` and `defineFluent()`
internally, preserving their naming, replacement and typed factory contracts.

For bundled features, see [Overview and loading](extensions.md).
For package manifests and capability contracts, see
[Discovery and capability providers](extension-discovery.md).

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
use Vanderlee\Comprehend\Extensions\Extraction\Extraction;
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

Run [the syntax extension example](https://github.com/vanderlee/Comprehend/blob/master/docs/examples/syntax-extensions.php) for checked examples.

## Composer discovery

See [Composer discovery](extension-discovery.md#composer-discovery).

## Capability providers

See [Capability providers](extension-discovery.md#capability-providers).

## Bundled ASCII vocabulary

See [ASCII identifiers and keywords](ascii-identifiers.md).

## Expression tables

See [Expressions](expressions.md).
