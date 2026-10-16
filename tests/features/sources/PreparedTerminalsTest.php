<?php

declare(strict_types=1);

namespace Tests\features\sources;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Directive\CaseSensitive;
use Vanderlee\Comprehend\Parser\Terminal\Range;
use Vanderlee\Comprehend\Parser\Terminal\Regex;
use Vanderlee\Comprehend\Parser\Terminal\Set;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;

uses(TestCase::class);

test('prepared terminals honor inherited case changes and alternate source modes', function () {
    foreach ([new Set('A'), new Range('A', 'Z'), new Regex('/A/'), Regex::literalWindow('A')] as $parser) {
        foreach ([new ByteSource('a'), new Utf8Source('a'), new ByteSource('a')] as $source) {
            $this->assertFalse($parser->parseAll($source)->success);
            $this->assertTrue((new CaseSensitive(false, $parser))->parseAll($source)->success);
            $this->assertFalse($parser->parseAll($source)->success);
        }
        $parser->caseInsensitive();
        $frozen = $parser->freeze();
        $parser->caseSensitive();
        $this->assertTrue($frozen->parseAll('a')->success);
        $this->assertFalse($parser->parseAll(new Utf8Source('a'))->success);
    }
});

test('cached set membership preserves exact keys exclusion and Unicode fold widths', function () {
    foreach ([true, false] as $include) {
        $parser = new Set("01\0K", $include);
        foreach (['0', '1', "\0", 'K', 'x'] as $unit) {
            $result = $parser->parseAll(new Utf8Source($unit));
            $this->assertSame($unit === 'x' ? !$include : $include, $result->success);
        }
        $parser->caseInsensitive();
        $this->assertSame($include, $parser->parseAll(new Utf8Source('K'))->success);
        $parser->caseSensitive();
        $this->assertSame(!$include, $parser->parseAll(new Utf8Source('K'))->success);
    }
});

test('successful byte preparation never hides invalid UTF-8 terminal data', function () {
    foreach ([new Set("\xff"), new Range("\xff", null), new Regex("/\xff/"), Regex::literalWindow("\xff")] as $parser) {
        $this->assertTrue($parser->parseAll(new ByteSource("\xff"))->success);
        foreach (['x', ''] as $input) {
            try {
                $parser->parseAll(new Utf8Source($input));
                $this->fail('Expected invalid UTF-8 terminal data');
            } catch (InvalidArgumentException $error) {
                $this->assertSame('Source must contain valid UTF-8', $error->getMessage());
            }
        }
        $this->assertTrue($parser->parseAll(new ByteSource("\xff"))->success);
    }
});
