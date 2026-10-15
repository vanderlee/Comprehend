<?php

namespace Tests\Src\Parser\Terminal;

use Vanderlee\Comprehend\Source\ByteSource;
use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\Utf8Source;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseLimitException;

abstract class TextTest extends ParserTestCase
{
    public static function textData()
    {
        return [
            [new Text('foo'), 'foo', 0, true, 3],
            [new Text('foo'), 'foobar', 0, true, 3],
            [new Text('FOO'), 'foo', 0, false, 0],
            [new Text('foo'), 'FOO', 0, false, 0],
            [new Text('foo'), 'faa', 0, false, 1],
            [new Text('bar'), 'foobar', 0, false, 0],
            [new Text('bar'), 'foobar', 3, true, 3],
            [new Text('bar'), 'foobaz', 3, false, 2],
            [(new Text('foo'))->caseInsensitive(), 'foo', 0, true, 3],
            [(new Text('foo'))->caseInsensitive(), 'FOO', 0, true, 3],
            [(new Text('FOO'))->caseInsensitive(), 'foo', 0, true, 3],
            [(new Text('FOO'))->caseInsensitive(), 'FOO', 0, true, 3],
        ];
    }
}

uses(TextTest::class);

test('construction', function () {
    $this->assertInstanceOf(Text::class, new Text('foo'));
});

test('empty', function () {
    $this->expectExceptionMessage('Empty argument');
    new Text('');
});

test('text', function (Text $parser, $input, $offset, $match, $length) {
    $this->assertResult($match, $length, $parser->matchPrefix(new ByteSource($input), $offset), (string) $parser);
})->with(TextTest::textData())->group('terminal', 'parser');

test('literal preparation stays separate for byte and UTF-8 recognition', function () {
    $parser = new Text('éx');
    foreach ([new ByteSource('éx'), new Utf8Source('éx'), new ByteSource('éx')] as $source) {
        $session = new ParseSession($source);
        $match = $parser->recognizeInSession($source, 0, $session);
        $this->assertTrue($match->accepted);
        $this->assertSame(3, $match->length);
        $this->assertSame($source instanceof ByteSource ? 4 : 3, $session->workUsed());
    }
});

test('invalid UTF-8 literals remain usable in byte mode after text validation fails', function () {
    $parser = new Text("\xff");
    $this->assertTrue($parser->parseAll(new ByteSource("\xff"))->success);
    try {
        $parser->parseAll(new Utf8Source('x'));
        $this->fail('Expected invalid UTF-8 literal');
    } catch (\InvalidArgumentException $error) {
        $this->assertSame('Source must contain valid UTF-8', $error->getMessage());
    }
    $this->assertTrue($parser->parseAll(new ByteSource("\xff"))->success);
});

test('literal comparison preserves Unicode progress and short-input diagnostics', function () {
    $parser = new Text('é🙂z');
    foreach (['é🙂!' => 6, 'é🙂' => 6, 'ê🙂z' => 0] as $input => $progress) {
        $result = $parser->parseAll(new Utf8Source($input));
        $this->assertFalse($result->success);
        $this->assertSame($progress, $result->farthestFailure);
    }
    $result = $parser->matchPrefix(new Utf8Source('!é🙂z?'), 1);
    $this->assertTrue($result->success);
    $this->assertSame('é🙂z', $result->rawText());
    $this->assertSame(1, $result->node->span->start);
    $this->assertSame(8, $result->node->span->end);
});

test('case insensitive literals preserve source widths and local policy changes', function () {
    $parser = (new Text('Kx'))->caseInsensitive();
    $this->assertSame('KX', $parser->parseAll(new Utf8Source('KX'))->rawText());
    $parser->caseSensitive();
    $this->assertFalse($parser->parseAll(new Utf8Source('KX'))->success);
    $this->assertTrue($parser->parseAll(new Utf8Source('Kx'))->success);
    $parser->caseInsensitive();
    $this->assertTrue($parser->parseAll(new Utf8Source('KX'))->success);
});

test('literal work limits preserve byte positions and restore case scope', function () {
    foreach (['é🙂z', 'é🙂!'] as $input) {
        $source = new Utf8Source($input);
        $session = new ParseSession($source, caseSensitive: false, limits: new ParseLimits(maxWork: 3));
        $parser = (new Text('é🙂z'))->caseSensitive();
        try {
            $parser->recognizeInSession($source, 0, $session);
            $this->fail('Expected work limit');
        } catch (ParseLimitException $error) {
            $this->assertSame(6, $error->byteOffset);
            $this->assertSame('work', $error->limit);
            $this->assertSame(3, $session->workUsed());
            $this->assertFalse($session->isCaseSensitive());
        }
    }
});
