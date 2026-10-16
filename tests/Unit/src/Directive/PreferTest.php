<?php

namespace Tests\Unit\src\Directive;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Directive\Prefer;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ChoicePolicy;
use Vanderlee\Comprehend\Source\ByteSource;

abstract class PreferTest extends ParserTestCase
{
    public static function preferData()
    {
        return [
            [new Prefer(ChoicePolicy::FIRST, new Choice(new Text('a'), new Text('aa'))), 'aa', 0, true, 1],
            [new Prefer(ChoicePolicy::FIRST, new Choice(new Text('aa'), new Text('a'))), 'aa', 0, true, 2],
            [new Prefer(ChoicePolicy::LONGEST, new Choice(new Text('a'), new Text('aa'))), 'aa', 0, true, 2],
            [new Prefer(ChoicePolicy::LONGEST, new Choice(new Text('aa'), new Text('a'))), 'aa', 0, true, 2],
            [new Prefer(ChoicePolicy::SHORTEST, new Choice(new Text('a'), new Text('aa'))), 'aa', 0, true, 1],
            [new Prefer(ChoicePolicy::SHORTEST, new Choice(new Text('aa'), new Text('a'))), 'aa', 0, true, 1],
        ];
    }
}

uses(PreferTest::class)->group('directive');

test('constructor', function () {
    /* @noinspection PhpParamsInspection */
    $this->assertInstanceOf(Prefer::class, new Prefer(ChoicePolicy::FIRST, new Text('a')));
});

test('empty', function () {
    $this->expectException(\TypeError::class);
    new Prefer(null, new Text('x'));
});

test('prefer', function (Prefer $parser, $input, $offset, $match, $length) {
    $this->assertResult($match, $length, $parser->matchPrefix(new ByteSource($input), $offset), (string) $parser);
})->with(PreferTest::preferData());
