<?php

namespace Tests\Unit\src\Parser\Output;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;

abstract class ResultTraitParserTest extends ParserTestCase
{
    public static function concatenatedValues(): array
    {
        return [
            'string' => ['text', 'text'],
            'integer' => [42, '42'],
            'float' => [1.5, '1.5'],
            'true' => [true, '1'],
            'false' => [false, ''],
            'null' => [null, ''],
            'Stringable' => [new class implements \Stringable {
                public function __toString(): string
                {
                    return 'object text';
                }
            }, 'object text'],
        ];
    }

    public static function invalidConcatenatedValues(): array
    {
        return [
            'array' => [[['nested']]],
            'object without string conversion' => [new \stdClass()],
        ];
    }
}

uses(ResultTraitParserTest::class);

test('concatenation converts supported values', function (mixed $value, string $expected): void {
    $mapping = static fn(): mixed => $value;
    $parsers = [
        (new Text('a'))->concatResult('value', $mapping),
        new Sequence(
            (new Text('a'))->setResult('value', $mapping),
            (new Nothing())->concatResult('value', ''),
        ),
        new Sequence(
            (new Text('a'))->pushResult('value', $mapping),
            (new Nothing())->concatResult('value', ''),
        ),
    ];

    foreach ($parsers as $index => $parser) {
        $result = $parser->parseAll(new ByteSource('a'));
        $output = $index === 2 ? [$expected] : $expected;

        $this->assertSame($output, $result->result('value'));
        $this->assertSame($output, $result->result('value'));
    }
})->with(ResultTraitParserTest::concatenatedValues());

test('invalid concatenated values fail lazily and retain their error', function (mixed $value): void {
    $calls = 0;
    $parser = (new Text('a'))->concatResult('value', static function () use ($value, &$calls): mixed {
        ++$calls;

        return $value;
    });
    $result = $parser->parseAll(new ByteSource('a'));
    $this->assertTrue($result->success);
    $this->assertSame(0, $calls);

    $firstError = null;
    for ($read = 0; $read < 2; ++$read) {
        try {
            $result->result('value');
            $this->fail('Expected an invalid concatenated value to be rejected');
        } catch (\UnexpectedValueException $error) {
            $firstError ??= $error;
            $this->assertSame($firstError, $error);
        }
    }
    $this->assertSame(1, $calls);
})->with(ResultTraitParserTest::invalidConcatenatedValues());

test('concatenation rejects an invalid existing value or pushed tail', function (mixed $value): void {
    foreach (['setResult', 'pushResult'] as $operation) {
        $parser = new Sequence(
            (new Text('a'))->$operation('value', static fn(): mixed => $value),
            (new Text('b'))->concatResult('value'),
        );
        $result = $parser->parseAll(new ByteSource('ab'));

        try {
            $result->result('value');
            $this->fail('Expected an invalid concatenation operand to be rejected');
        } catch (\UnexpectedValueException $error) {
            $this->assertStringContainsString('Concatenated results', $error->getMessage());
        }
    }
})->with(ResultTraitParserTest::invalidConcatenatedValues());

test('concat flat', function () {
    $parser = new Sequence(
        (new Text('foo'))->setResult('word'),
        new Text('bar'),
        (new Text('baz'))->concatResult('word'),
    );
    $match = $parser->matchPrefix(new ByteSource('foobarbaz'));
    $this->assertResult(true, 9, $match);
    $this->assertEquals('foobaz', $match->results()['word']);
    $this->assertEquals('foobaz', $match->result('word'));
});

test('concat array', function () {
    $parser = new Sequence(
        (new Text('foo'))->pushResult('word'),
        new Text('bar'),
        (new Text('baz'))->concatResult('word'),
    );
    $match = $parser->matchPrefix(new ByteSource('foobarbaz'));
    $this->assertResult(true, 9, $match);
    $this->assertEquals(['foobaz'], $match->results()['word']);
    $this->assertEquals(['foobaz'], $match->result('word'));
});

test('flat to array', function () {
    $parser = new Sequence(
        (new Text('foo'))->setResult('word'),
        new Text('bar'),
        (new Text('baz'))->pushResult('word'),
    );
    $match = $parser->matchPrefix(new ByteSource('foobarbaz'));
    $this->assertResult(true, 9, $match);
    $this->assertEquals(['foo', 'baz'], $match->results()['word']);
    $this->assertEquals(['foo', 'baz'], $match->result('word'));
});

test('default result', function () {
    $parser = new Sequence((new Text('foo'))->setResult(), new Text('bar'), (new Text('baz'))->concatResult());
    $match = $parser->matchPrefix(new ByteSource('foobarbaz'));
    $this->assertResult(true, 9, $match);
    $this->assertEquals('foobaz', $match->result());
    $this->assertEquals('foobaz', $match->result());
    $this->assertEmpty($match->results());
});

test('set result value', function () {
    $parser = (new Text('foo'))->setResult('var', 'found it');
    $match = $parser->matchPrefix(new ByteSource('foobar'));
    $this->assertResult(true, 3, $match);
    $this->assertEquals('found it', $match->results()['var']);
    $this->assertEquals('found it', $match->result('var'));
});

test('set result value callback', function () {
    $parser = (new Text('foo'))->setResult(
        'var',
        function ($text) {
            return strrev($text);
        },
    );
    $match = $parser->matchPrefix(new ByteSource('foobarbaz'));
    $this->assertResult(true, 3, $match);
    $this->assertEquals('oof', $match->results()['var']);
    $this->assertEquals('oof', $match->result('var'));
});

test('concat result', function () {
    $parser = new Sequence(
        (new Text('foo'))->concatResult('var'),
        // initial
        (new Text('bar'))->concatResult('var', 'BAR'),
        // concat w/ value
        (new Text('baz'))->concatResult(
            'var',
            function ($text) {
                // concat w/ callback
                return strrev($text);
            },
        ),
    );
    $match = $parser->matchPrefix(new ByteSource('foobarbazbob'));
    $this->assertResult(true, 9, $match);
    $this->assertEquals('fooBARzab', $match->results()['var']);
    $this->assertEquals('fooBARzab', $match->result('var'));
});

test('push result', function () {
    $parser = new Sequence(
        (new Text('foo'))->pushResult('var'),
        // initial
        (new Text('bar'))->pushResult('var', 'BAR'),
        // push w/ value
        (new Text('baz'))->pushResult(
            'var',
            function ($text) {
                // push w/ callback
                return strrev($text);
            },
        ),
    );
    $match = $parser->matchPrefix(new ByteSource('foobarbazbob'));
    $this->assertResult(true, 9, $match);
    $this->assertEquals(['foo', 'BAR', 'zab'], $match->results()['var']);
    $this->assertEquals(['foo', 'BAR', 'zab'], $match->result('var'));
});

test('push and concat preserve an existing null result', function () {
    $calls = 0;
    $nullable = (new Text('a'))->setResult('value', static function () use (&$calls) {
        ++$calls;

        return null;
    });

    $pushed = (new Sequence($nullable, (new Text('b'))->pushResult('value')))->parseAll(new ByteSource('ab'));
    $this->assertSame(0, $calls);
    $this->assertSame([null, 'b'], $pushed->result('value'));
    $this->assertSame([null, 'b'], $pushed->result('value'));
    $this->assertSame(1, $calls);

    $concatenated = (new Sequence(
        $nullable,
        (new Text('b'))->concatResult('value'),
    ))->parseAll(new ByteSource('ab'));
    $this->assertSame('b', $concatenated->result('value'));
    $this->assertSame('b', $concatenated->result('value'));
    $this->assertSame(2, $calls);
});
