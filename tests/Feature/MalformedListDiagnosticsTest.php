<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Parser\Structure\Not;

uses(TestCase::class);

test('failed delimited lists retain malformed item positions through nested rollback', function (
    string $input,
    int $offset,
    int $line,
    int $column,
): void {
    $item = C::text("\u{e9}");
    $list = $item->separatedBy(',')->between('[', ']');
    $nested = $list->separatedBy(',')->between('[', ']')->spacing(C::set(" \t\r\n")->many());
    $result = $nested->parse($input);

    $this->assertFalse($result->success);
    $this->assertSame(0, $result->consumedByteLength);
    $this->assertSame($offset, $result->diagnostic->byteOffset);
    $this->assertSame(['line' => $line, 'column' => $column], $result->diagnostic->location());
    $this->assertContains('"' . "\u{e9}" . '"', $result->diagnostic->expected);
})->with([
    ['[[?]]', 2, 1, 3],
    ["[[\u{e9},?]]", 5, 1, 5],
    ["[[\u{e9},\n?]]", 6, 2, 1],
    ["[[\u{e9},\r\n?]]", 7, 2, 1],
    ["[[\u{e9},]]", 5, 1, 5],
]);

test('list prefix diagnostics stay silent but complete parsing retains the failed extension', function (
    bool $allowTrailing,
): void {
    $list = C::text('abc')->separatedBy(',', allowTrailing: $allowTrailing);
    $prefix = $list->matchPrefix('abc,abx');
    $this->assertTrue($prefix->success);
    $this->assertSame($allowTrailing ? 'abc,' : 'abc', $prefix->rawText());
    $this->assertSame(['abc'], $prefix->value());
    $this->assertNull($prefix->diagnostic);
    $failure = $list->parse('abc,abx');
    $this->assertFalse($failure->success);
    $this->assertSame(6, $failure->diagnostic->byteOffset);
    $this->assertContains('"abc"', $failure->diagnostic->expected);
})->with([false, true]);

test('valid empty and trailing lists have no termination diagnostics', function (string $input): void {
    $list = C::text('a')->separatedBy(',', allowTrailing: true)->between('[', ']');
    $result = $list->parse($input);
    $this->assertTrue($result->success);
    $this->assertNull($result->diagnostic);
    $this->assertSame($input, $result->rawText());
})->with(['[]', '[a]', '[a,]']);

test('a missing close retains tied delimiter and separator expectations', function (): void {
    $result = C::text('a')->separatedBy(',')->between('[', ']')->parse('[a');
    $this->assertFalse($result->success);
    $this->assertSame(2, $result->diagnostic->byteOffset);
    $this->assertContains('"]"', $result->diagnostic->expected);
    $this->assertContains('","', $result->diagnostic->expected);
});

test('partial separators and required items retain the farther failure', function (string $input, int $minimum): void {
    $result = C::text('abc')->separatedBy('::', min: $minimum)->parse($input);
    $this->assertFalse($result->success);
    $this->assertSame(4, $result->diagnostic->byteOffset);
    $this->assertContains('"::"', $result->diagnostic->expected);
})->with([['abc:x', 0], ['abc:x', 2]]);

test('discarded optional repetition and negative lookahead restore deferred list diagnostics', function (
    string $probe,
): void {
    $list = C::text('a')->separatedBy(',')->between('[', ']');
    $parser = match ($probe) {
        'optional' => $list->optionalValue()->ignoreThen('!'),
        'repeat' => $list->manyValues()->ignoreThen('!'),
        default => C::of(new Not($list->parser()))->ignoreThen('!'),
    };
    $result = $parser->parse('[a,?]');
    $this->assertFalse($result->success);
    $this->assertSame(0, $result->diagnostic->byteOffset);
    $this->assertSame(['"!"'], $result->diagnostic->expected);
})->with(['optional', 'repeat', 'not']);
