<?php

namespace Tests\Unit\src\Directive;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Directive\CaseSensitive;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;

abstract class CaseSensitiveTest extends ParserTestCase
{
    public static function caseSensitiveData()
    {
        return [
            [new CaseSensitive(true, new Text('foo')), 'foo', 0, true, 3],
            [new CaseSensitive(true, new Text('foo')), 'fOO', 0, false, 1],
            [new CaseSensitive(false, new Text('foo')), 'foo', 0, true, 3],
            [new CaseSensitive(false, new Text('foo')), 'fOO', 0, true, 3],
            [new CaseSensitive(false, new Text('FOO')), 'foo', 0, true, 3],
        ];
    }
}

uses(CaseSensitiveTest::class)->group('directive');

test('constructor', function () {
    $this->assertInstanceOf(CaseSensitive::class, new CaseSensitive(true, new Text('a')));
});

test('case sensitive', function (CaseSensitive $parser, $input, $offset, $match, $length) {
    $this->assertResult($match, $length, $parser->matchPrefix(new ByteSource($input), $offset), (string) $parser);
})->with(CaseSensitiveTest::caseSensitiveData());
