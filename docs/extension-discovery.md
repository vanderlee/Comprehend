# Discovery and capability providers

Package manifests advertise syntax factories and other extension capabilities.
For factory implementations, see [Writing syntax extensions](syntax-extensions.md);
for everyday usage, see [Overview and loading](extensions.md).

## Composer discovery

Bundled extensions, installed Composer packages, and the root application can
advertise providers in `extensions/*.extension.json` or `extensions/*/*.extension.json`. Each file can declare a syntax
factory class and several capability providers; its filename is independent of their classes. For example,
`extensions/Identifiers/Identifiers.extension.json`:

```json
{
  "class": "Acme\\Parsing\\Identifiers",
  "rules": ["identifier"],
  "fluents": ["identifier"]
}
```

Files directly in the package's `extensions/` directory or one directory below it
are discovered. Deeper directories and other JSON files are ignored. Filenames are processed in
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

Each factory provider implements `SyntaxExtension`, is autoloadable, and has a public
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
Loading never installs dependencies or performs network requests. Discovery and
provider construction happen during authoring or grammar preparation, before recognition.

## Capability providers

Use `providers` to map autoloadable classes to the PHP interfaces they advertise.
Several classes may provide the same interface. Capability-only manifests can omit
`class`, `rules`, and `fluents`:

```json
{
  "providers": {
    "Acme\\Parsing\\FirstPass": ["Acme\\Parsing\\AnalysisPass"],
    "Acme\\Parsing\\SecondPass": ["Acme\\Parsing\\AnalysisPass"]
  }
}
```

Consumers request contracts through `Syntax\ExtensionLoader`:

```php
use Vanderlee\Comprehend\Syntax\ExtensionLoader;

$passes = ExtensionLoader::defaults()->all(\Acme\Parsing\AnalysisPass::class);
```

`all(interface)` returns providers in declaration/discovery order, or an empty list.
`requireOne(interface)` requires exactly one advertised provider and reports missing
or ambiguous capabilities. Both methods have generic return types for static analysis.
Consumers decide how and when to invoke providers; discovery never runs their operations.

Contracts must be interfaces. Names are case-insensitive, accept a leading backslash,
and are deduplicated and sorted for declaration comparison. Discovery validates metadata
without loading provider classes or interfaces. Interface existence and implementation
are checked when a provider is needed; every interface it advertises must validate.
Only advertised interfaces resolve: implemented parent interfaces are not inferred.

Provider classes require a public constructor with no required arguments and must
remain stateless. The loader shares one instance per package/class across all its
contracts and factory registrations. Each provider loads atomically: interface checks,
construction, and declared syntax registrations must all succeed before anything is
cached. Failed loads can be retried. Recursive loads fail with manifest context.

The existing `class`/`rules`/`fluents` fields declare the syntax factory provider and
implicitly advertise `SyntaxExtension`. When that class also appears in `providers`,
its capabilities are combined and use the same instance. Explicit `SyntaxExtension`
declarations must belong to that syntax class and have both name lists. Existing
factory-only manifests need no changes; additional factory classes can use separate manifests.

The bundled Optimizer advertises `Builder\GraphOptimization` in its `providers` map.
Grammar preparation uses `requireOne(GraphOptimization::class)` when optimization is
enabled; `FreezeFlags::NONE` bypasses that lookup. Multiple optimizer providers remain
an error for freezing, even though `all()` supports multiple providers for other consumers.
The old `optimizer` flag and top-level `provides` list are rejected; use the map.
See [Grammar authoring](grammar-authoring.md) for optimizer eligibility rules.

The bundled Optimizer also implements `SyntaxExtension`. Its manifest advertises
`readWhile`, `readUntil`, `readWhileSet`, `readWhileRange`, `readRest`, `skipWhile`,
`skipUntil`, and `skipRest` as rules, with an empty fluent list. These factories
append scanners when chained, just like other terminal factories. Their parsers
live in `Extensions\Optimizer\Parser`, and explicitly authored scanners work with
`FreezeFlags::NONE`. Factory loading and optimizer resolution share the same provider
instance. See [Reading and skipping runs](read-scanning.md).

