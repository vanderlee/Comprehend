<?php

declare(strict_types=1);

namespace Tests\Feature\semantics;

use LogicException;
use RuntimeException;
use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Source\Utf8Source;

abstract class SemanticValueTest extends ParserTestCase
{
    public static function typedValues(): array
    {
        return [[42], [false], [null], [['number' => 42]], [(object) ['number' => 42]]];
    }
}

uses(SemanticValueTest::class);

test('values are lazy and cached including null and objects', function ($value) {
    $calls = 0;
    $parser = (new Text('a'))->mapValue(
        function (ParseResult $result) use (&$calls, $value) {
            ++$calls;
            $this->assertSame('a', $result->rawText());

            return $value;
        },
    );
    $result = $parser->parseAll(new Utf8Source('a'));
    $this->assertSame(0, $calls);
    $this->assertSame($value, $result->value());
    $this->assertSame($value, $result->value());
    $this->assertSame(1, $calls);
})->with(SemanticValueTest::typedValues());

test('mapping and named results have recognition owned lifetime', function () {
    $calls = 0;
    $parser = (new Text('42'))->name('number')->setResult(
        'number',
        function ($text) use (&$calls) {
            ++$calls;

            return (int) $text;
        },
    )->mapValue(fn(ParseResult $result): int => $result->result('number') * 2);
    $first = $parser->parseAll(new Utf8Source('42'));
    $parser->setResult('number', 99)->mapValue(fn(): string => 'later');
    $second = $parser->parseAll(new Utf8Source('42'));
    $this->assertSame(84, $first->value());
    $this->assertSame(84, $first->value());
    $this->assertSame(['number' => 42], $first->results());
    $this->assertSame(['number' => 42], $first->results());
    $this->assertSame(1, $calls);
    $this->assertSame('later', $second->value());
    $this->assertSame(['number' => 99], $second->results());
    $this->assertSame(2, $calls);
    $this->assertSame('42', $first->rawText());
    $this->assertSame('number', $first->ast()->name);
    $this->assertSame([0, 2], [$first->ast()->span->start, $first->ast()->span->end]);
    $this->assertSame('42', $first->node->rawText());
});

test('each recognition has its own cache', function () {
    $calls = 0;
    $parser = (new Text('a'))->mapValue(
        function () use (&$calls) {
            return ++$calls;
        },
    );
    $first = $parser->matchPrefix(new Utf8Source('a'));
    $second = $parser->matchPrefix(new Utf8Source('a'));
    $this->assertSame(1, $first->value());
    $this->assertSame(2, $second->value());
    $this->assertSame(1, $first->value());
});

test('failures and discarded alternatives never evaluate mapping', function () {
    $calls = 0;
    $mapped = (new Text('ab'))->mapValue(
        function () use (&$calls) {
            ++$calls;

            return 42;
        },
    );
    $this->assertNull($mapped->parseAll(new Utf8Source('abx'))->value());
    $this->assertNull($mapped->matchPrefix(new Utf8Source('x'))->value());
    $result = Choice::longest(
        (new Text('a'))->mapValue(
            function () use (&$calls) {
                ++$calls;

                return 0;
            },
        ),
        $mapped,
    )->parseAll(new Utf8Source('ab'));
    $this->assertSame('ab', $result->value());
    $this->assertSame(0, $calls);
});

test('default value is raw text separate from default result', function () {
    $result = (new Text('é'))->setResult(null, 'changed')->parseAll(new Utf8Source('é'));
    $this->assertSame('é', $result->value());
    $this->assertSame('changed', $result->result());
});

test('thrown mapping is evaluated once and error is retained', function () {
    $calls = 0;
    $failure = new RuntimeException('mapping failed');
    $result = (new Text('a'))->mapValue(
        function () use (&$calls, $failure) {
            ++$calls;
            throw $failure;
        },
    )->parseAll(new Utf8Source('a'));
    for ($i = 0; $i < 2; ++$i) {
        try {
            $result->value();
            $this->fail('Expected cached mapping error');
        } catch (RuntimeException $error) {
            $this->assertSame($failure, $error);
        }
    }
    $this->assertSame(1, $calls);
});

test('recursive value read is rejected', function () {
    $result = (new Text('a'))->mapValue(fn(ParseResult $result) => $result->value())->parseAll(new Utf8Source('a'));
    $this->expectException(LogicException::class);
    $result->value();
});
