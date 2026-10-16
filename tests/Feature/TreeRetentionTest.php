<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Directive\Lexeme;
use Vanderlee\Comprehend\Extensions\Binding;
use Vanderlee\Comprehend\Extensions\Recovery as RecoveryExtension;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\Utf8Source;

uses(TestCase::class);

test('value-only parsing preserves nested optional values and per-result caches', function (): void {
    $syntax = Binding::syntax();
    $number = $syntax->integer(0, 9)->mapText('intval');
    $list = $number->separatedBy(',')->between('[', ']');
    $parser = $syntax->collect($number->optionalValue(false)->between('(', ')'), $list)
        ->map(static fn(array $parts): object => (object) ['parts' => $parts])
        ->spacing(C::text(' ')->many());
    $grammar = $parser->freeze();
    foreach ([$parser, $grammar] as $author) {
        $result = $author->parse('() [1, 2]', trees: false);
        $this->assertTrue($result->success);
        $this->assertNull($result->node);
        $this->assertNull($result->ast());
        $this->assertSame('() [1, 2]', $result->rawText());
        $this->assertSame([false, [1, 2]], $result->value()->parts);
        $this->assertSame($result->value(), $result->value());
        $this->assertNotSame($result->value(), $author->parse('() [1, 2]', trees: false)->value());
        $this->assertNotNull($author->parse('() [1, 2]')->node);
    }
});

test('tree-free directives preserve selected values and independent outer mapping', function (): void {
    $item = (new Text("\u{e9}"))->map(static fn(string $s): string => 'child');
    $outer = (new Lexeme($item))->map(static fn(string $s): string => $s . '-outer');
    $source = new Utf8Source("x\u{e9}!");
    $prefix = $outer->matchPrefix($source, 1, trees: false);
    $this->assertSame('child-outer', $prefix->value());
    $this->assertSame("\u{e9}", $prefix->rawText());
    $this->assertSame([1, 3], [$prefix->start, $prefix->end]);
    $this->assertSame('child', $item->matchPrefix($source, 1, trees: false)->value());
    $this->assertFalse($outer->parseAll($source, 1, trees: false)->success);
});

test('tree-free diagnostics match ordinary recognition through malformed list rollback', function (): void {
    $list = Binding::syntax()->text('abc')->separatedBy(',')->between('[', ']');
    $ordinary = $list->parse('[abc,abx]');
    $lean = $list->parse('[abc,abx]', trees: false);
    $this->assertFalse($lean->success);
    $this->assertSame($ordinary->diagnostic->byteOffset, $lean->diagnostic->byteOffset);
    $this->assertSame($ordinary->diagnostic->expected, $lean->diagnostic->expected);
    $this->assertSame($ordinary->diagnostic->rulePaths, $lean->diagnostic->rulePaths);
});

test('tree-free callbacks retain collections validation effects and cached mapping errors', function (): void {
    $events = [];
    $parser = C::text('a')->setResult('letter')->mapValue(static function (ParseResult $result) use (&$events): string {
        $events[] = 'value';
        return strtoupper($result->rawText());
    })->validateSemantic(static fn(ParseResult $result): bool => $result->value() === 'A')
        ->onCommit(static function (ParseResult $result) use (&$events): void {
            $events[] = $result->rawText();
        });
    $result = $parser->parse('a', trees: false);
    $this->assertSame([], $events);
    $this->assertSame(['letter' => 'a'], $result->results());
    $this->assertTrue($result->commit());
    $this->assertTrue($result->commit());
    $this->assertSame(['value', 'a'], $events);
    $error = new RuntimeException('mapping');
    $failed = C::text('a')->mapValue(static fn(): never => throw $error)->parse('a', trees: false);
    for ($i = 0; $i < 2; ++$i) {
        try {
            $failed->value();
            $this->fail('Expected cached mapping failure');
        } catch (RuntimeException $caught) {
            $this->assertSame($error, $caught);
        }
    }
});

test('intrinsic tree requirements are rejected before recognition', function (bool $dynamicName): void {
    $calls = 0;
    $callback = static function () use (&$calls, $dynamicName): string|bool {
        ++$calls;
        return $dynamicName ? 'name' : true;
    };
    $child = $dynamicName ? C::text('b')->named($callback) : C::text('b')->guard($callback);
    $parser = C::text('a')->then($child);
    try {
        $parser->parse('ab', trees: false);
        $this->fail('Expected a tree requirement error');
    } catch (LogicException $error) {
        $this->assertStringContainsString('requires a CST', $error->getMessage());
    }
    $this->assertSame(0, $calls);
    $this->assertTrue($parser->parse('ab')->success);
    $this->assertSame(1, $calls);
})->with([false, true]);

test('custom parser tree requirements apply to direct sessions too', function (): void {
    $parser = new class extends Parser {
        public function requiresTree(): bool
        {
            return true;
        }

        protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
        {
            throw new RuntimeException('must not recognize');
        }

        public function __toString(): string
        {
            return 'tree-dependent';
        }
    };
    $source = new Utf8Source('a');
    $this->expectException(LogicException::class);
    $parser->recognizeInSession($source, 0, new ParseSession($source, trees: false));
});

test('recovery retains errors and values without synthetic tree nodes', function (): void {
    $syntax = Binding::syntax()->extend(new RecoveryExtension());
    $parser = $syntax->text('a')->recoverItem(',', ']')->between('[', ']');
    $result = $parser->parse('[a,bad,a]', trees: false);
    $this->assertTrue($result->success);
    $this->assertNull($result->node);
    $this->assertNull($result->ast());
    $this->assertSame('a', $result->value()[0]);
    $this->assertSame('a', $result->value()[2]);
    $this->assertCount(1, $result->recoveries());
    $this->assertSame('bad', $result->recoveries()[0]->span->rawText());
    $this->assertFalse($result->commit());
});
