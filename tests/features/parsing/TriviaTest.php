<?php

declare(strict_types=1);

namespace Tests\features\parsing;

use Vanderlee\Comprehend\Source\ByteSource;
use Tests\ParserTestCase;
use Vanderlee\Comprehend\Directive\Space;
use Vanderlee\Comprehend\Directive\Trivia;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Regex;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\Utf8Source;
use Vanderlee\Comprehend\Tree\ConcreteNode;

uses(ParserTestCase::class);

test('trivia subclass is classified before dynamic naming and omitted from ast', function (): void {
    $trivia = new class (new Text(' ')) extends Trivia {
        public function __toString(): string
        {
            return 'custom trivia';
        }
    };
    $namingSawTrivia = false;
    $trivia->name(static function (ConcreteNode $node) use (&$namingSawTrivia): string {
        $namingSawTrivia = $node->isTrivia;

        return 'space';
    });
    $parser = (new Sequence($trivia, (new Text('a'))->name('letter')))->name('root');
    $result = $parser->parseAll(new ByteSource(' a'));

    $this->assertTrue($result->success);
    $this->assertTrue($namingSawTrivia);
    $this->assertTrue($result->node->children[0]->isTrivia);
    $this->assertSame($trivia::class, $result->node->children[0]->kind);
    $this->assertFalse($result->node->children[1]->isTrivia);
    $this->assertSame(' a', $result->rawText());
    $this->assertCount(1, $result->ast()->children);
    $this->assertSame('letter', $result->ast()->children[0]->name);
});

test('explicit and automatic trivia round trip nested unnamed input', function () {
    $trivia = (new Regex('~(?:\s|/\*[^*]*\*/)*~'))->lexeme()->trivia();
    $body = (new Sequence(
        new Text('('),
        new Sequence(new Text('a'), new Text('b')),
        new Text(')'),
    ))->spacing($trivia);
    $parser = (new Sequence($trivia, $body, $trivia))->spacing(null);
    $text = " \n( /*first*/ a\tb /*last*/ ) \n";
    $result = $parser->parseAll(new Utf8Source($text));
    $this->assertTrue($result->success);
    $this->assertSame($text, $result->node->rawText());
    $children = $result->node->children;
    $this->assertTrue($children[0]->isTrivia);
    $this->assertTrue($children[2]->isTrivia);
    $this->assertSame($text, implode('', array_map(fn($node) => $node->rawText(), $children)));
    $bodyChildren = $children[1]->children;
    $this->assertSame(
        ['(', ' /*first*/ ', "a\tb", ' /*last*/ ', ')'],
        array_map(fn($node) => $node->rawText(), $bodyChildren),
    );
    $this->assertTrue($bodyChildren[1]->isTrivia);
    $this->assertTrue($bodyChildren[2]->children[1]->isTrivia);
    $this->assertSame("\t", $bodyChildren[2]->children[1]->rawText());
});

test('zero width trivia retains actual position', function () {
    $trivia = Repeat::star(new Text(' '))->trivia();
    $result = (new Sequence($trivia, new Text('a'), $trivia))->parseAll(new Utf8Source('a'));
    $this->assertTrue($result->success);
    $this->assertSame([0, 0], [$result->node->children[0]->span->start, $result->node->children[0]->span->end]);
    $this->assertSame([1, 1], [$result->node->children[2]->span->start, $result->node->children[2]->span->end]);
    $this->assertTrue($result->node->children[0]->isTrivia);
});

test('utf8 trivia has whole code point byte spans', function () {
    $trivia = Repeat::star(new Text(" "))->trivia();
    $result = (new Sequence($trivia, new Text('é'), $trivia))->parseAll(new Utf8Source(" é "));
    $this->assertTrue($result->success);
    $this->assertSame([0, 2], [$result->node->children[0]->span->start, $result->node->children[0]->span->end]);
    $this->assertSame([4, 6], [$result->node->children[2]->span->start, $result->node->children[2]->span->end]);
    $this->assertSame(" ", $result->node->children[2]->rawText());
});

test('lexeme disables nested overrides and restores outer spacing', function () {
    $literal = (new Sequence(new Text('a'), new Text('b')))->spacing(new Text(' '));
    $parser = (new Sequence($literal->lexeme(), new Text('c')))->spacing(new Text(' '));
    $this->assertTrue($parser->parseAll(new Utf8Source('ab c'))->success);
    $this->assertFalse($parser->parseAll(new Utf8Source('a b c'))->success);
    $this->assertTrue($literal->parseAll(new Utf8Source('a b'))->success);
    $this->assertTrue($parser->matchPrefix(new ByteSource('ab c'))->success);
    $this->assertFalse($parser->matchPrefix(new ByteSource('a b c'))->success);
});

test('lexeme scope restores after failed alternative', function () {
    $choice = new Choice(
        (new Sequence(new Text('a'), new Text('x')))->lexeme(),
        new Sequence(new Text('a'), new Text('b')),
    );
    $parser = new Space(new Text(' '), $choice);
    $this->assertTrue($parser->parseAll(new Utf8Source('a b'))->success);
});

test('explicit literal spaces still match inside lexeme', function () {
    $parser = (new Sequence(new Text('a'), new Text(' '), new Text('b')))->spacing(new Text(' '))->lexeme();
    $this->assertTrue($parser->parseAll(new Utf8Source('a b'))->success);
    $this->assertFalse($parser->parseAll(new Utf8Source('ab'))->success);
});

test('repeat retains only accepted intervening trivia', function () {
    $calls = 0;
    $spacer = (new Text(' '))->onCommit(
        function () use (&$calls) {
            ++$calls;
        },
    );
    $parser = Repeat::plus(new Text('a'))->spacing($spacer);
    $result = $parser->matchPrefix(new Utf8Source('a a '));
    $this->assertSame('a a', $result->node->rawText());
    $this->assertCount(3, $result->node->children);
    $this->assertTrue($result->node->children[1]->isTrivia);
    $this->assertTrue($result->commit());
    $this->assertSame(1, $calls);
    $calls = 0;
    $this->assertFalse($parser->parseAll(new Utf8Source('a a '))->success);
    $this->assertSame(0, $calls);
});

test('failed repeat extension keeps diagnostics only when minimum is unmet', function (): void {
    $source = new ByteSource('ab ac');
    $optionalExtension = (new Repeat(new Text('ab'), 1))->spacing(new Text(' '));
    $requiredExtension = (new Repeat(new Text('ab'), 2))->spacing(new Text(' '));

    $accepted = $optionalExtension->matchPrefix($source);
    $this->assertTrue($accepted->success);
    $this->assertSame('ab', $accepted->rawText());
    $this->assertCount(1, $accepted->node->children);
    $this->assertNull($accepted->diagnostic);

    $rejected = $requiredExtension->matchPrefix($source);
    $this->assertFalse($rejected->success);
    $this->assertSame(0, $rejected->consumedByteLength);
    $this->assertSame(4, $rejected->diagnostic->byteOffset);
});

test('automatic trivia is concrete and omitted from named ast and results', function () {
    $spacer = (new Text(' '))->name('space')->setResult('space');
    $parser = (new Sequence(
        (new Text('a'))->name('a'),
        (new Text('b'))->name('b'),
    ))->name('root')->spacing($spacer);
    $result = $parser->parseAll(new Utf8Source('a b'));
    $this->assertSame([], $result->results());
    $this->assertCount(2, $result->ast()->children);
    $this->assertCount(3, $result->node->children);
    $this->assertTrue($result->node->children[1]->isTrivia);
});
