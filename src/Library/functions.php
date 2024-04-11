<?php

namespace Vanderlee\Comprehend\Library;

use Exception;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Char;
use Vanderlee\Comprehend\Parser\Terminal\Range;
use Vanderlee\Comprehend\Parser\Terminal\Regex;
use Vanderlee\Comprehend\Parser\Terminal\Set;
use Vanderlee\Comprehend\Parser\Terminal\Text;

function plus($parser)
{
    return Repeat::plus($parser);
}

function star($parser)
{
    return Repeat::star($parser);
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

function range($from, $to)
{
    return new Range($from, $to);
}

/**
 * @throws Exception
 */
function set(string $set)
{
    return new Set($set);
}

function regex(string $pattern)
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
