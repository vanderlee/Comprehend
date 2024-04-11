Comprehend - a PHP *BNF parser framework
========================================
Build object oriented [LR(1)](https://en.wikipedia.org/wiki/Canonical_LR_parser) lexer, tokenizers and parsers in PHP using BNF-based syntax.

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
### ABNF
    word	= [A-Za-z]+
    list	= word *[ ',' word ]    
### Comprehend, using objects:
    $word	= new Repeat(new Regex('/[a-z][A-Z]/'), 1);
    $list	= new Sequence($word, new Repeat(new Sequence(',', $word)));
### Comprehend, using objects and array notation:
    $word	= new Repeat(new Regex('/[a-z][A-Z]/'), 1);
    $list	= new Sequence($word, new Repeat([',', $word]));
### Comprehend, using library functions:
    $word	= plus(regex('/[a-z][A-Z]/'));
    $list	= s($word, star([',', $word]));
### Comprehend, using Ruleset constructor
    $list   = new Ruleset([
        'word'           => plus(regex('/[a-z][A-Z]/')), 
        Ruleset::ROOT => s($word, star([',', $word])),
    ]);