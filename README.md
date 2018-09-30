Comprehend - a PHP *BNF parser framework
========================================
v1.0.1

Copyright &copy; 2011-2018 Martijn W. van der Lee [Toyls.com](https://toyls.com)

[MIT licensed](http://www.opensource.org/licenses/mit-license.php)

Build [LR(1)](https://en.wikipedia.org/wiki/Canonical_LR_parser) parsers in PHP with ease.

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/vanderlee/Comprehend/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/vanderlee/Comprehend/?branch=master)
[![Codacy Badge](https://api.codacy.com/project/badge/Grade/c065154c0f524d55b6767f6ed8a18657)](https://www.codacy.com/app/vanderlee/Comprehend?utm_source=github.com&amp;utm_medium=referral&amp;utm_content=vanderlee/Comprehend&amp;utm_campaign=Badge_Grade)
[![Build Status](https://travis-ci.org/vanderlee/Comprehend.svg?branch=master)](https://travis-ci.org/vanderlee/Comprehend)

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