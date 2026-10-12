Comprehend - a PHP *BNF parser framework
========================================
Build parsers and token trees in PHP using interpreted parser combinators and
BNF-inspired grammar composition.

A scannerless parser combinator framework for PHP, using recursive descent with
ordered choice and greedy repetition. Grammars are constructed programmatically
using BNF-inspired composition.

[![Packagist](https://img.shields.io/packagist/v/vanderlee/comprehend.svg)](https://packagist.org/packages/vanderlee/comprehend)
[![PHP from Packagist](https://img.shields.io/packagist/php-v/vanderlee/comprehend.svg)](http://php.net/supported-versions.php)
[![Packagist](https://img.shields.io/packagist/l/vanderlee/comprehend.svg)](http://www.opensource.org/licenses/mit-license.php)

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/vanderlee/Comprehend/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/vanderlee/Comprehend/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/vanderlee/Comprehend/badges/build.png?b=master)](https://scrutinizer-ci.com/g/vanderlee/Comprehend/build-status/master)
[![Code Coverage](https://scrutinizer-ci.com/g/vanderlee/Comprehend/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/vanderlee/Comprehend/?branch=master)
[![Codacy Badge](https://api.codacy.com/project/badge/Grade/c065154c0f524d55b6767f6ed8a18657)](https://www.codacy.com/app/vanderlee/Comprehend?utm_source=github.com&amp;utm_medium=referral&amp;utm_content=vanderlee/Comprehend&amp;utm_campaign=Badge_Grade)
![Travis (.org)](https://img.shields.io/travis/vanderlee/Comprehend.svg?label=Travis-CI)
[![Maintainability](https://api.codeclimate.com/v1/badges/3bd38eba7f4912bf2f66/maintainability)](https://codeclimate.com/github/vanderlee/Comprehend/maintainability)


Copyright &copy; 2011-2024 Martijn W. van der Lee [Toyls.com](https://toyls.com), MIT license applies.

Project status
--------------
Comprehend is beta software. Its parser primitives and test suite are mature,
but parts of the documentation and higher-level tooling are still incomplete.
The minimum supported PHP version is 8.2; the former `Match` base class was
renamed to `AbstractMatch` because `match` became a reserved keyword in PHP 8.
Runtime extensions required: DOM, JSON, and mbstring. CI covers PHP 8.2 through
8.5 with PHPUnit 9.6; PHP 8.6 is an experimental, non-blocking job.

Grammars are currently constructed programmatically from parser objects or a
`Ruleset`. Comprehend does not read BNF or EBNF grammar files directly.

Documentation
-------------
The documentation is maintained in the [`docs`](docs) directory:

- [Tutorial](docs/tutorial.md)
- [Builder](docs/builder.md)
- [Facade](docs/facade.md)
- [Bundled RFC libraries](docs/libraries.md)
- [Reference](docs/reference.md)
- [Frequently asked questions](docs/faq.md)
- [Contributing](docs/contribute.md)

Choosing a grammar style
------------------------
Use a `Ruleset` for most complete grammars. It names rules and resolves
recursive references automatically. Direct parser objects are useful for small
compositions or custom parsers, but recursive object graphs need explicit
`Stub` placeholders. Both styles use the same parser primitives underneath.

Comprehend does not have separate generated lexer and parser phases. Terminal
parsers recognize input; assigning token names to parser rules makes the result
usable as a token stream or syntax tree. Parsers are interpreted at runtime and
are not compiled into generated PHP source.

Execution behavior
------------------
`match()` recognizes a prefix at the supplied offset. Source-based `matchPrefix()`
and `parseAll()` return canonical prefix/full-input results; full-input parsing
requires explicit trailing trivia in the grammar. Legacy grammars can append
`End` for complete ASCII input. Choice selects the first successful alternative
by default; explicit first, longest, and shortest policies are available.
Repetition is greedy. A later sequence failure does not retry an earlier
successful choice or shorten a repeat.

The runtime has no LR item sets, parse tables, or shift/reduce engine. Recursive
references support grammars that consume input before recurring; direct and
indirect left recursion are unsupported. There is no memoization or guaranteed
linear parse time.

Legacy `match()` Unicode limitation: matching mixes byte indexing and multibyte lengths,
while token/result slicing uses bytes. Multibyte input can be accepted incorrectly
or produce truncated UTF-8 output, including when `End` is used. Use ASCII input
for the current matching contract; declaring mbstring does not fix this defect.
The explicit `matchSource(new Utf8Source($input))` path now recognizes UTF-8 with
byte offsets and complete code-point reads. Use `ByteSource` for octet grammars.
See [Source matching](docs/source-matching.md) for mode rules and custom parser
compatibility. Phase 1.4 adds [canonical results and explicit legacy coordinate
adapters](docs/parse-results.md), preserving the existing string entry points.

See [Execution behavior and limitations](docs/execution.md) for checked examples,
grammar factoring, and recursion guidance. Run the examples with
`php docs/examples/execution.php` after installing dependencies.
See [Prefix and full-input entry points](docs/consumption.md) for paired examples.

Features
--------
 -	Closely follows BNF syntax using objects as operands.
 -  Includes various pre-defined RFC syntax rules.
 -	Whitespace skipping.
 -	Support for tokenizing.
 -  Add your own custom parsers.
 -  Create full sets of rules.
 -	Optional case (in)sensitivity.

Example
-------
### Imports used below
    use Vanderlee\Comprehend\Builder\Ruleset;
    use Vanderlee\Comprehend\Parser\Structure\Repeat;
    use Vanderlee\Comprehend\Parser\Structure\Sequence;
    use Vanderlee\Comprehend\Parser\Terminal\Regex;
    use function Vanderlee\Comprehend\Library\plus;
    use function Vanderlee\Comprehend\Library\regex;
    use function Vanderlee\Comprehend\Library\s;
    use function Vanderlee\Comprehend\Library\star;

### Grammar sketch (BNF-inspired pseudocode)
    word	= [A-Za-z]+
    list	= word *[ ',' word ]    
### Comprehend, using objects:
    $word	= new Repeat(new Regex('/[A-Za-z]/'), 1);
    $list	= new Sequence($word, new Repeat(new Sequence(',', $word)));
### Comprehend, using objects and array notation:
    $word	= new Repeat(new Regex('/[A-Za-z]/'), 1);
    $list	= new Sequence($word, new Repeat([',', $word]));
### Comprehend, using library functions:
    $word	= plus(regex('/[A-Za-z]/'));
    $list	= s($word, star([',', $word]));
### Comprehend, using Ruleset constructor
    $list = new Ruleset();
    $list->define('word', plus(regex('/[A-Za-z]/')));
    $list->define(Ruleset::ROOT, s($list->word, star([',', $list->word])));
