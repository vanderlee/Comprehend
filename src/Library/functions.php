<?php

declare (strict_types=1);

namespace Vanderlee\Comprehend\Library;

use Exception;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Char;
use Vanderlee\Comprehend\Parser\Terminal\Range;
use Vanderlee\Comprehend\Parser\Terminal\Regex;
use Vanderlee\Comprehend\Parser\Terminal\Set;
use Vanderlee\Comprehend\Parser\Terminal\Text;

function plus(Parser $parser): Repeat
{
    return Repeat::plus($parser);
}

function star(Parser $parser): Repeat
{
    return Repeat::star($parser);
}

function opt(Parser $parser): Repeat
{
    return Repeat::optional($parser);
}

function repeat(int $from, ?int $to, Parser $parser): Repeat
{
    return new Repeat($parser, $from, $to);
}

function s(Parser ...$parsers): Sequence
{
    return new Sequence(...$parsers);
}

function c(Parser ...$choices): Choice
{
    return new Choice(...$choices);
}

function range(?string $from, ?string $to): Range
{
    return new Range($from, $to);
}

/**
 * @throws Exception
 */
function set(string $set): Set
{
    return new Set($set);
}

function regex(string $pattern): Regex
{
    return new Regex($pattern);
}

function char(string $char): Char
{
    return new Char($char);
}

function text(string $text): Text
{
    return new Text($text);
}
