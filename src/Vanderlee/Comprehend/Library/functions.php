<?php

namespace vanderlee\comprehend\library;

use vanderlee\comprehend\parser\structure\Choice;
use vanderlee\comprehend\parser\structure\Repeat;
use vanderlee\comprehend\parser\structure\Sequence;
use vanderlee\comprehend\parser\terminal\Char;
use vanderlee\comprehend\parser\terminal\Range;
use vanderlee\comprehend\parser\terminal\Regex;
use vanderlee\comprehend\parser\terminal\Set;
use vanderlee\comprehend\parser\terminal\Text;

function plus($parser)
{
    return Repeat::oneOrMore($parser);
}

function star($parser)
{
    return Repeat::zeroOrMore($parser);
}

function opt($parser)
{
    return Repeat::optional($parser);
}

function repeat($from, $to, $parser)
{
    return new Repeat($parser, $from, $to);
}

function s(...$parsers)
{
    return new Sequence(...$parsers);
}

function c(...$choices)
{
    return new Choice(...$choices);
}

function longest(...$choices)
{
    return Choice::longest(...$choices);
}

function range($from, $to)
{
    return new Range($from, $to);
}

function set($set)
{
    return new Set($set);
}

function regex($pattern)
{
    return new Regex($pattern);
}

function char($char)
{
    return new Char($char);
}

function text($text)
{
    return new Text($text);
}