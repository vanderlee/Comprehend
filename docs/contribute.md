# Contributing
Want to contribute to Comprehend? Here's some important things you need to know!

## Parsers
Custom parsers extend `Parser` and implement protected
`parse(&$input, $offset, Context $context)` plus `__toString()`. Recognition
returns a match through the inherited factories; keep callbacks deferred and
restore scoped Context overrides on every exit path.

Before changing protected signatures or input units, read the phase 1.1
[source coordinate and extension contract](source-and-extension-contract.md).
It inventories current subclasses, records adapter policies and explains the
limits of legacy multibyte offsets. The current runtime still has the
[documented Unicode limitation](execution.md#current-unicode-limitation).

## Libraries
TODO: Instructions on how to make custom parser libraries.

## Where?
TODO: Where to find issue tracking, downloads, etc.

## Unittesting
Use PHP 8.2 or newer with DOM, JSON, and mbstring enabled. Install development
dependencies with `composer install`, then run `composer check-platform-reqs` and
`php vendor/phpunit/phpunit/phpunit --do-not-cache-result` with PHPUnit 9.6.
The default `phpunit.xml` runs the suite in `tests` and limits coverage to `src`.
If a coverage driver is enabled, Clover output is written to `build/logs/clover.xml`.

CI requires the suite and Composer platform checks to pass on PHP 8.2, 8.3, 8.4,
and 8.5. PHP 8.6 runs experimentally and may fail without blocking the workflow.
Add each new stable supported PHP branch to the required matrix after checking
compatibility; retain PHP 8.2 as the floor until a major release changes it.

Runtime requirements are declared in Composer's `require` section. Development
tools and their additional extensions are declared through `require-dev` and
checked by Composer. Coverage additionally needs PCOV or Xdebug; ordinary CI
tests run without a coverage driver. PHPUnit 6 and PHP 7 are no longer supported.

Upgrading to this baseline requires PHP 8.2+; PHP 8.0 and 8.1 are also below the
new minimum. Run `composer update` if an existing local lock file still records
the old platform requirements. This library does not commit `composer.lock`,
so clean CI resolves dependencies on each tested runtime. Subsequent PHP floor
increases and the typed runtime redesign belong in a new major release.

Token XML tests compare element order, local names, namespace URIs, and text.
Token groups become namespace URIs, sanitized groups become prefixes, and
sanitized token names (or parser class names) become local names. DOM/libxml may
place namespace declarations on different ancestors across versions; declaration
placement and redundant declarations are not part of the serialization contract.

## Integration
TODO: Integrating with Laravel/Symfony/Dingo/etc.

## Inspiration
TODO: List of things on the to-do list or possible future directions.
