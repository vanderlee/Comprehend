<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\FreezeFlags;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Except;
use Vanderlee\Comprehend\Parser\Structure\Not;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\SeparatedList;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Regex;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;

uses(TestCase::class);

test('labels replace local entry expectations without erasing preceding alternatives', function (int $flags): void {
    $group = (new Choice(new Text('b'), new Text('c')))->label('letter');
    $parser = new Choice(new Text('a'), $group);
    $frozen = $parser->freeze($flags);
    for ($i = 0; $i < 2; ++$i) {
        $result = $frozen->parse('?');
        $this->assertFalse($result->success);
        $this->assertSame(0, $result->diagnostic->byteOffset);
        $this->assertSame(['"a"', 'letter'], $result->diagnostic->expected);
        $this->assertSame([['Choice', 'Text'], ['Choice', 'Choice']], $result->diagnostic->rulePaths);
    }
})->with([FreezeFlags::NONE, FreezeFlags::OPTIMIZE]);

test('alternative labels accumulate and an outer entry label replaces nested labels', function (int $flags): void {
    $parser = new Choice((new Text('a'))->label('alpha'), (new Text('b'))->label('beta'));
    $this->assertSame(['alpha', 'beta'], $parser->freeze($flags)->parse('?')->diagnostic->expected);
    $parser->label('letter');
    $this->assertSame(['letter'], $parser->freeze($flags)->parse('?')->diagnostic->expected);
})->with([FreezeFlags::NONE, FreezeFlags::OPTIMIZE]);

test('labels preserve partial literals and deeper sequence errors', function (int $flags): void {
    $literal = (new Text('abcd'))->label('keyword')->freeze($flags)->parse('ab?');
    $this->assertSame(2, $literal->diagnostic->byteOffset);
    $this->assertSame(['"abcd"'], $literal->diagnostic->expected);
    $this->assertSame(0, $literal->consumedByteLength);
    $sequence = (new Sequence(new Text('a'), new Text('b')))->label('pair')->freeze($flags);
    $this->assertSame(['pair'], $sequence->parse('?')->diagnostic->expected);
    $deeper = $sequence->parse('a?');
    $this->assertSame(1, $deeper->diagnostic->byteOffset);
    $this->assertSame(['"b"'], $deeper->diagnostic->expected);
})->with([FreezeFlags::NONE, FreezeFlags::OPTIMIZE]);

test('a labelled alternative cannot replace an earlier farther failure', function (int $flags): void {
    $parser = new Choice(new Text('ab'), (new Text('z'))->label('zed'));
    $result = $parser->freeze($flags)->parse('ax');
    $this->assertSame(1, $result->diagnostic->byteOffset);
    $this->assertSame(['"ab"'], $result->diagnostic->expected);
})->with([FreezeFlags::NONE, FreezeFlags::OPTIMIZE]);

test('successful labels preserve alternative observations', function (int $flags): void {
    $parser = (new Choice((new Text('a'))->label('alpha'), new Text('b')))->label('letter');
    $result = $parser->freeze($flags)->parse('b');
    $this->assertTrue($result->success);
    $this->assertSame(['alpha'], $result->diagnostic->expected);
    $this->assertSame('b', $result->rawText());
})->with([FreezeFlags::NONE, FreezeFlags::OPTIMIZE]);

test('optional repeat and lookahead checkpoints discard labelled probes', function (int $flags): void {
    $optional = new Sequence(Repeat::optional((new Text('a'))->label('alpha')), new Text('z'));
    $this->assertSame(['"z"'], $optional->freeze($flags)->parse('?')->diagnostic->expected);
    $repeat = new Sequence(Repeat::star((new Text('a'))->label('alpha')), new Text('!'));
    $this->assertSame(['"!"'], $repeat->freeze($flags)->parse('a?')->diagnostic->expected);
    $lookahead = new Sequence(new Not((new Text('a'))->label('alpha')), new Text('b'));
    $this->assertSame(['"b"'], $lookahead->freeze($flags)->parse('?')->diagnostic->expected);
    $required = Repeat::plus((new Text('a'))->label('alpha'));
    $this->assertSame(['alpha'], $required->freeze($flags)->parse('?')->diagnostic->expected);
})->with([FreezeFlags::NONE, FreezeFlags::OPTIMIZE]);

test('labels preserve deferred malformed-list diagnostics', function (int $flags): void {
    $item = (new Regex('/[0-9]+/A'))->label('number');
    $list = new SeparatedList($item, new Text(','));
    $parser = (new Sequence($list, new Text('!')))->label('document');
    $result = $parser->freeze($flags)->parse('1,?');
    $this->assertSame(2, $result->diagnostic->byteOffset);
    $this->assertSame(['number'], $result->diagnostic->expected);
})->with([FreezeFlags::NONE, FreezeFlags::OPTIMIZE]);

test('labels are separate from rule paths and concrete names in every authoring style', function (): void {
    $expression = C::syntax()->text('x')->label('identifier')->diagnosticRule('item')->named('node');
    $this->assertSame(['identifier'], $expression->parse('?')->diagnostic->expected);
    $this->assertSame([['item']], $expression->parse('?')->diagnostic->rulePaths);
    $labelled = $expression->parse('x');
    $plain = C::text('x')->diagnosticRule('item')->named('node')->parse('x');
    $this->assertSame($plain->node->toArray(), $labelled->node->toArray());
    $this->assertSame('x', $labelled->value());
    $rules = (new Ruleset())->define('item', C::text('x')->label('identifier'));
    $this->assertSame(['identifier'], $rules->ref('item')->parseAll(new ByteSource('?'))->diagnostic->expected);
});

test('fluent labels are immutable and frozen labels survive builder mutation', function (int $flags): void {
    $base = C::text('x');
    $labelled = $base->label('identifier');
    $this->assertSame(['"x"'], $base->parse('?')->diagnostic->expected);
    $this->assertSame(['identifier'], $labelled->parse('?')->diagnostic->expected);
    $parser = (new Text('x'))->label('old label');
    $frozen = (new Choice($parser, new Text('z')))->freeze($flags);
    $retained = $frozen->parse('?');
    $parser->label('new label');
    $this->assertSame(['old label', '"z"'], $retained->diagnostic->expected);
    $this->assertSame(['old label', '"z"'], $frozen->parse('?')->diagnostic->expected);
    $this->assertSame(['new label'], $parser->parseAll(new ByteSource('?'))->diagnostic->expected);
    $sequence = C::text('a')->text('b')->label('pair')->then('c');
    $this->assertSame(['pair'], $sequence->freeze($flags)->parse('?')->diagnostic->expected);
})->with([FreezeFlags::NONE, FreezeFlags::OPTIMIZE]);

test('guard failures are labelled only at the entry position', function (): void {
    $empty = (new Nothing())->guard(static fn(): bool => false)->label('condition');
    $this->assertSame(['condition'], $empty->parseAll(new ByteSource(''))->diagnostic->expected);
    $nonempty = (new Text('x'))->guard(static fn(): bool => false)->label('keyword');
    $result = $nonempty->parseAll(new ByteSource('x'));
    $this->assertSame(1, $result->diagnostic->byteOffset);
    $this->assertSame(['guard(Text)'], $result->diagnostic->expected);
});

test('lookahead and exclusion rejection can carry entry labels despite observed progress', function (int $flags): void {
    $not = (new Not(new Text('a')))->label('non-a');
    $this->assertSame(['non-a'], $not->freeze($flags)->parse('a')->diagnostic->expected);
    $except = (new Except(new Text('a'), new Text('a')))->label('allowed letter');
    $this->assertSame(['allowed letter'], $except->freeze($flags)->parse('a')->diagnostic->expected);
})->with([FreezeFlags::NONE, FreezeFlags::OPTIMIZE]);

test('labels retain canonical UTF-8 byte offsets and diagnostic columns', function (): void {
    $source = new Utf8Source("\u{e9}?");
    $result = (new Text('a'))->label('letter')->matchPrefix($source, 2);
    $this->assertSame(2, $result->diagnostic->byteOffset);
    $this->assertSame(['line' => 1, 'column' => 2], $result->diagnostic->location());
    $this->assertSame(['letter'], $result->diagnostic->expected);
});

test('throwing labelled recognition restores parser and rule scopes', function (): void {
    $source = new ByteSource('x');
    $session = new ParseSession($source);
    $error = new RuntimeException('guard failed');
    $parser = (new Text('x'))->label('identifier')->diagnosticRule('throwing')
        ->guard(static fn(): never => throw $error);
    try {
        $parser->recognizeInSession($source, 0, $session);
        $this->fail('Expected the guard exception');
    } catch (RuntimeException $caught) {
        $this->assertSame($error, $caught);
    }
    $result = (new Text('b'))->label('beta')->recognizeInSession($source, 0, $session);
    $this->assertSame(['beta'], $result->diagnostic->expected);
    $this->assertSame([['Text']], $result->diagnostic->rulePaths);
});

test('blank expectation labels are rejected', function (string $label): void {
    $this->expectException(InvalidArgumentException::class);

    (new Text('x'))->label($label);
})->with(['', ' ', "\t\n"]);
