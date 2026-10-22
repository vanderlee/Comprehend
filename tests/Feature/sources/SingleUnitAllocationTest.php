<?php

declare(strict_types=1);

namespace Tests\Feature\sources;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\SingleUnitHooks;
use Tests\Fixtures\SingleUnitSource;
use Vanderlee\Comprehend\Builder\FreezeFlags;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Terminal\Any;
use Vanderlee\Comprehend\Parser\Terminal\Char;
use Vanderlee\Comprehend\Parser\Terminal\Range;
use Vanderlee\Comprehend\Parser\Terminal\Set;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8FileSource;
use Vanderlee\Comprehend\Source\Utf8Source;
use Vanderlee\Comprehend\Tree\ConcreteNode;

uses(TestCase::class);

dataset('single unit span reuse', [
    'ASCII character' => [static fn() => new Char('a'), 'a', true],
    'two-byte character' => [static fn() => new Char("\u{e9}"), "\u{e9}", true],
    'three-byte character' => [static fn() => new Char("\u{20ac}"), "\u{20ac}", true],
    'four-byte character' => [static fn() => new Char("\u{1f642}"), "\u{1f642}", true],
    'excluded character' => [static fn() => new Char('x', false), "\u{e9}", true],
    'range' => [static fn() => new Range("\u{e0}", "\u{ff}"), "\u{e9}", true],
    'excluded range' => [static fn() => new Range('a', 'z', false), "\u{1f642}", true],
    'set' => [static fn() => new Set("\u{20ac}\u{e9}"), "\u{20ac}", true],
    'excluded set' => [static fn() => new Set('a', false), "\u{1f642}", true],
    'any' => [static fn() => new Any(), "\u{1f642}", true],
    'byte character' => [static fn() => new Char("\xff"), "\xff", false],
    'byte range' => [static fn() => new Range("\x80", "\xff"), "\xff", false],
    'byte set' => [static fn() => new Set("\0\xff"), "\xff", false],
    'byte any' => [static fn() => new Any(), "\xff", false],
]);

test('single unit spans bind trees collections values and committed effects without a second span', function (
    callable $factory,
    string $text,
    bool $utf8,
): void {
    $events = [];
    $assigned = null;
    $parser = $factory()->name('unit')->tag('terminal')->setResult('unit')->assignTo($assigned)
        ->guard(static fn(ConcreteNode $node): bool => $node->rawText() === $text)
        ->mapValue(static fn(ParseResult $result): object => (object) ['text' => $result->rawText()])
        ->validateSemantic(static fn(ParseResult $result): bool => $result->value()->text === $text)
        ->onCommit(static function (ParseResult $result) use (&$events): void {
            $events[] = [$result->start, $result->end, $result->rawText()];
        });
    foreach ([$parser, $parser->freeze(FreezeFlags::NONE), $parser->freeze()] as $grammar) {
        $events = [];
        $assigned = null;
        $input = 'x' . $text . '!';
        $source = new SingleUnitSource($utf8 ? new Utf8Source($input) : new ByteSource($input));
        $result = $grammar->matchPrefix($source, 1);
        $this->assertTrue($result->success);
        $this->assertSame($source->lastUnit, $result->span);
        $this->assertSame($result->span, $result->node->span);
        $this->assertSame(1, $source->spanCalls);
        $this->assertSame([1, 1 + strlen($text)], [$result->start, $result->end]);
        $this->assertSame($text, $result->rawText());
        $this->assertSame('unit', $result->node->name);
        $this->assertSame([], $result->node->children);
        $this->assertSame(['terminal'], $result->node->tags);
        $this->assertSame($text, $result->result('unit'));
        $value = $result->value();
        $this->assertSame($text, $value->text);
        $this->assertSame($value, $result->value());
        $this->assertNull($assigned);
        $this->assertSame([], $events);
        $this->assertTrue($result->commit());
        $this->assertTrue($result->commit());
        $this->assertSame($text, $assigned);
        $this->assertSame([[1, 1 + strlen($text), $text]], $events);
    }
})->with('single unit span reuse');

test('invalid UTF-8 literals still reject before reading input including EOF after byte-mode use', function (
    Parser $parser,
): void {
    $this->assertTrue($parser->parseAll(new ByteSource("\xff"))->success);
    foreach (['a', ''] as $input) {
        $source = new SingleUnitSource(new Utf8Source($input));
        try {
            $parser->matchPrefix($source);
            $this->fail('Invalid UTF-8 literal was accepted');
        } catch (InvalidArgumentException $error) {
            $this->assertSame('Source must contain valid UTF-8', $error->getMessage());
        }
        $this->assertSame(0, $source->unitReads);
    }
    $this->assertTrue($parser->parseAll(new ByteSource("\xff"))->success);
})->with([
    'character' => [new Char("\xff")],
    'range' => [new Range("\x80", "\xff")],
    'set' => [new Set("\xff")],
]);

test('reused file unit spans retain page-crossing text and closed-source checks', function (callable $factory): void {
    $stream = tmpfile();
    fwrite($stream, "x\u{1f642}!");
    $file = new Utf8FileSource($stream, pageSize: 4);
    fclose($stream);
    try {
        $result = $factory()->matchPrefix(new SingleUnitSource($file), 1);
        $this->assertTrue($result->success);
        $this->assertSame("\u{1f642}", $result->rawText());
        $this->assertSame([1, 5], [$result->start, $result->end]);
        $file->close();
        $this->expectException(LogicException::class);
        $result->rawText();
    } finally {
        $file->close();
    }
})->with([
    'character' => [static fn() => new Char("\u{1f642}")],
    'range' => [static fn() => new Range("\u{1f600}", "\u{1f64f}")],
    'set' => [static fn() => new Set("\u{1f642}")],
    'any' => [static fn() => new Any()],
]);

test('terminal subclasses retain match hooks and restore scopes when hooks throw', function (
    callable $factory,
    bool $accepted,
    bool $throw,
): void {
    $parser = $factory();
    if (!$parser instanceof Any) {
        $parser->caseInsensitive();
    }
    $source = new ByteSource($accepted ? 'A' : ($parser instanceof Any ? '' : '?'));
    $session = new ParseSession($source);
    $parser->session = $session;
    $parser->throwInHook = $throw;
    try {
        $match = $parser->recognizeInSession($source, 0, $session);
        $this->assertFalse($throw);
        $this->assertSame($accepted, $match->accepted);
    } catch (LogicException $error) {
        $this->assertTrue($throw);
        $this->assertSame('unit hook failed', $error->getMessage());
    }
    $expected = $parser instanceof Char || $parser instanceof Set ? ['makeMatch'] : [];
    $expected[] = $accepted ? 'success' : 'failure';
    $this->assertSame($expected, $parser->hooks);
    $this->assertSame($parser instanceof Any, $parser->caseInHook);
    $this->assertTrue($session->isCaseSensitive());
    $this->assertSame([], $session->rulePath());
})->with([
    'character' => [static fn() => new class ('a') extends Char {
        use SingleUnitHooks;
    }],
    'range' => [static fn() => new class ('a', 'z') extends Range {
        use SingleUnitHooks;
    }],
    'set' => [static fn() => new class ('a') extends Set {
        use SingleUnitHooks;
    }],
    'any' => [static fn() => new class extends Any {
        use SingleUnitHooks;
    }],
])->with([false, true])->with([false, true]);

test('mismatches keep zero-width failure spans rather than the rejected unit span', function (Parser $parser): void {
    $source = new SingleUnitSource(new Utf8Source('x?'));
    $result = $parser->matchPrefix($source, 1);
    $this->assertFalse($result->success);
    $this->assertSame([1, 1, 0], [$result->start, $result->end, $result->consumedByteLength]);
    $this->assertSame(1, $result->diagnostic->byteOffset);
    $this->assertNotSame($source->lastUnit, $result->span);
    $this->assertSame(2, $source->spanCalls);
})->with([new Char('a'), new Range('a', 'z'), new Set('a')]);

test('single unit EOF failures remain zero width', function (Parser $parser): void {
    $source = new SingleUnitSource(new Utf8Source('x'));
    $result = $parser->matchPrefix($source, 1);
    $this->assertFalse($result->success);
    $this->assertSame([1, 1], [$result->start, $result->end]);
    $this->assertNull($source->lastUnit);
    $this->assertSame(1, $source->spanCalls);
})->with([new Char('a'), new Range('a', 'z'), new Set('a'), new Any()]);

test('shared unit spans keep values trees and commit state independent', function (callable $factory): void {
    $source = new SingleUnitSource(new Utf8Source("\u{e9}"));
    $source->reuseUnits = true;
    $events = [];
    $parser = $factory()->mapValue(static fn(ParseResult $result): object => (object) ['text' => $result->rawText()])
        ->onCommit(static function (ParseResult $result) use (&$events): void {
            $events[] = $result->value();
        });
    $first = $parser->parseAll($source);
    $second = $parser->parseAll($source);
    $this->assertSame($first->span, $second->span);
    $this->assertNotSame($first->node, $second->node);
    $firstValue = $first->value();
    $secondValue = $second->value();
    $this->assertNotSame($firstValue, $secondValue);
    $this->assertSame($firstValue, $first->value());
    $this->assertSame($secondValue, $second->value());
    $this->assertTrue($first->commit());
    $this->assertTrue($first->commit());
    $this->assertFalse($second->isCommitted());
    $this->assertSame([$firstValue], $events);
    $this->assertTrue($second->commit());
    $this->assertSame([$firstValue, $secondValue], $events);
})->with([
    'character' => [static fn() => new Char("\u{e9}")],
    'range' => [static fn() => new Range("\u{e0}", "\u{ff}")],
    'set' => [static fn() => new Set("\u{e9}")],
    'any' => [static fn() => new Any()],
]);
