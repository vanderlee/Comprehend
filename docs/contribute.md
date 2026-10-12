# Contributing
Want to contribute to Comprehend? Here's some important things you need to know!

## Parsers
TODO: Instructions on the architecture of parsers.

## Libraries
TODO: Instructions on how to make custom parser libraries.

## Where?
TODO: Where to find issue tracking, downloads, etc.

## Unittesting
Use PHP 8.2 or newer. Install development dependencies with `composer install`, then run
`php vendor/phpunit/phpunit/phpunit --do-not-cache-result` with PHPUnit 9.6.
The default `phpunit.xml` runs the suite in `tests` and limits coverage to `src`.
If a coverage driver is enabled, Clover output is written to `build/logs/clover.xml`.

Token XML tests compare element order, local names, namespace URIs, and text.
Token groups become namespace URIs, sanitized groups become prefixes, and
sanitized token names (or parser class names) become local names. DOM/libxml may
place namespace declarations on different ancestors across versions; declaration
placement and redundant declarations are not part of the serialization contract.

## Integration
TODO: Integrating with Laravel/Symfony/Dingo/etc.

## Inspiration
TODO: List of things on the to-do list or possible future directions.
