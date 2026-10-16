<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Binding;
use Vanderlee\Comprehend\Parser\Terminal\ReadWhile;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;
use Vanderlee\Comprehend\Source\Utf8FileSource;
use Vanderlee\Comprehend\Result\ParseResult;

uses(TestCase::class);

test('read scanners return text and stop before rejected units', function (
    C $parser,
    string $input,
    string $text,
): void {
    $result = $parser->freeze()->matchPrefix($input);
    $this->assertTrue($result->success);
    $this->assertSame($text, $result->value());
    $this->assertSame($text, $result->rawText());
    $this->assertSame(strlen($text), $result->consumedByteLength);
    $this->assertNotNull($result->node);
    $this->assertSame($text, $result->node->rawText());
    $this->assertSame([], $result->node->children);
})->with([
    [C::readWhile(static fn(string $c): bool => $c !== ';'), 'abc;def', 'abc'],
    [C::readWhile(static fn(string $c): bool => $c !== ';'), ';', ''],
    [C::readUntil(';'), 'abc;def', 'abc'],
    [C::readUntil(';'), ';', ''],
    [C::readUntil(';'), 'abc', 'abc'],
    [C::readUntil('*/'), 'a*b*/tail', 'a*b'],
    [C::readUntil('aba'), 'aaba!', 'a'],
    [C::readUntil(static fn(string $c): bool => $c === ';'), 'abc;', 'abc'],
    [C::readWhileSet('é🙂'), '🙂éx', '🙂é'],
    [C::readWhileRange('α', 'ω'), 'αβγ!', 'αβγ'],
    [C::readWhileRange(null, '9'), '12A', '12'],
    [C::readWhileRange('a', null), 'abc0', 'abc'],
    [C::readRest(), '🙂;abc', '🙂;abc'],
    [C::readRest(), '', ''],
]);

test('bounds count source units and never probe after max', function (): void {
    $seen = [];
    $parser = C::readWhile(static function (string $c) use (&$seen): bool {
        $seen[] = $c;
        return true;
    }, min: 2, max: 2);
    $result = $parser->matchPrefix('é🙂!');
    $this->assertSame('é🙂', $result->value());
    $this->assertSame(['é', '🙂'], $seen);
    $this->assertSame('', C::readWhile(static function (): bool {
        throw new LogicException('Must not probe');
    }, max: 0)->matchPrefix('x')->value());
    $this->assertSame('ab', C::readUntil(';', max: 2)->matchPrefix('abc;')->value());
    $this->assertSame('12', C::readWhileRange('0', '9', max: 2)->matchPrefix('123')->value());
    $this->assertSame('aa', C::readWhileSet('a', max: 2)->matchPrefix('aaa')->value());
});

test('minimum failures preserve observed byte progress without consumption', function (C $parser): void {
    $result = $parser->matchPrefix('é!');
    $this->assertFalse($result->success);
    $this->assertSame(0, $result->consumedByteLength);
    $this->assertSame(2, $result->farthestFailure);
    $this->assertNotEmpty($result->diagnostic->expected);
})->with([
    [C::readWhile(static fn(string $c): bool => $c === 'é', min: 2)],
    [C::readUntil('!', min: 2)],
    [C::readWhileSet('é', min: 2)],
    [C::readWhileRange('é', 'é', min: 2)],
]);

test('read bounds reject invalid configurations', function (int $min, ?int $max): void {
    $this->expectException(InvalidArgumentException::class);
    C::readWhileSet('a', min: $min, max: $max);
})->with([[-1, null], [0, -1], [2, 1]]);

test('literal delimiter strings are never interpreted as callable names', function (): void {
    $this->assertSame('abc', C::readUntil('strlen')->matchPrefix('abcstrlenx')->value());
    $this->expectException(InvalidArgumentException::class);
    C::readUntil('');
});

test('byte scanners support arbitrary octets and nonzero offsets', function (): void {
    $source = new ByteSource("x\xff\x80\0;");
    $this->assertSame("\xff\x80\0", C::readWhileSet("\xff\x80\0")->matchPrefix($source, 1)->value());
    $this->assertSame("\xff\x80\0", C::readUntil(';')->matchPrefix($source, 1)->value());
    $this->assertSame("\xff\x80\0;", C::readRest()->parse($source, 1)->value());
    $this->assertSame("\xff\x80", C::readWhileRange("\x80", "\xff")->matchPrefix($source, 1)->value());
});

test('scanners respect case policy except raw predicates', function (): void {
    $this->assertSame('Éé', C::readWhileSet('é')->caseSensitive(false)->matchPrefix('Éé!')->value());
    $this->assertSame('ABC', C::readWhileRange('a', 'z')->caseSensitive(false)->matchPrefix('ABC!')->value());
    $this->assertSame('text', C::readUntil('ÉND')->caseSensitive(false)->matchPrefix('texténd!')->value());
    $this->assertSame('', C::readWhile(static fn(string $c): bool => $c === 'a')
        ->caseSensitive(false)->matchPrefix('A')->value());
});

test('skip scanners discard values while retaining source and trees', function (C $parser): void {
    $result = $parser->matchPrefix('abc;');
    $this->assertTrue($result->success);
    $this->assertNull($result->value());
    $this->assertSame('abc', $result->rawText());
    $this->assertNotNull($result->node);
})->with([
    [C::skipWhile(static fn(string $c): bool => $c !== ';')],
    [C::skipUntil(';')],
]);

test('scanner composition retains delimiters, spacing and binding values', function (): void {
    $parser = Binding::syntax()->readUntil(';')->thenIgnore(';');
    $this->assertSame('abc', $parser->parse('abc;')->value());
    $this->assertFalse($parser->parse('abc')->success);
    $this->assertSame('x y', C::text('x')->text('y')->spacing(C::readWhileSet(' '))->parse('x y')->rawText());
    $this->assertSame('xabc', C::text('x')->readRest()->parse('xabc')->value());
    $this->assertNull(C::skipRest()->parse('abc')->value());
});

test('scanner exceptions restore the owning session', function (): void {
    $source = new Utf8Source('A');
    $session = new ParseSession($source);
    $parser = (new ReadWhile(static function (): bool {
        throw new LogicException('predicate failed');
    }))->caseInsensitive();
    try {
        $parser->recognizeInSession($source, 0, $session);
        $this->fail('Expected predicate exception');
    } catch (LogicException $error) {
        $this->assertSame('predicate failed', $error->getMessage());
    }
    $this->assertTrue($session->isCaseSensitive());
    $this->assertTrue((new ReadWhile(static fn(string $c): bool => $c === 'A'))
        ->recognizeInSession($source, 0, $session)->accepted);
});

test('scanners enforce work limits', function (C $parser): void {
    $this->expectException(ParseLimitException::class);
    $parser->parse('abcdef', limits: new ParseLimits(maxWork: 3));
})->with([
    [C::readWhile(static fn(string $c): bool => true)],
    [C::readUntil(';')],
    [C::readWhileSet('abcdef')],
    [C::readWhileRange('a', 'z')],
    [C::readRest()],
]);

test('literal delimiters respect utf8 boundaries and partial suffixes', function (): void {
    $this->assertSame('é', C::readUntil('🙂!')->matchPrefix('é🙂!tail')->value());
    $this->assertSame('abc*', C::readUntil('*/')->parse('abc*')->value());
    $this->assertSame('é', C::readUntil(';')->matchPrefix('xé;', 1)->value());
    $this->expectException(InvalidArgumentException::class);
    C::readUntil(';')->matchPrefix('é;', 1);
});

test('invalid text patterns are rejected without restricting byte mode', function (C $parser): void {
    $this->expectException(InvalidArgumentException::class);
    $parser->matchPrefix('abc');
})->with([
    [C::readWhileSet("\xff")],
    [C::readUntil("\xff")],
    [C::readWhileRange('ab', 'z')],
    [C::readWhileRange('z', 'a')],
]);

test('frozen scanners reuse source-mode and case-specific state safely', function (): void {
    $parser = C::readWhileSet('é')->caseSensitive(false)->freeze();
    $this->assertSame('É', $parser->parse('É')->value());
    $this->assertSame("\xc3", $parser->matchPrefix(new ByteSource("\xc3!"))->value());
    $this->assertSame('é', $parser->parse('é')->value());
    $range = C::readWhileRange('a', 'z')->caseSensitive(false)->freeze();
    $this->assertSame('ABC', $range->parse(new ByteSource('ABC'))->value());
    $this->assertSame('abc', $range->parse('abc')->value());
});

test('scanners work across file pages through the source interface', function (): void {
    $stream = tmpfile();
    $this->assertIsResource($stream);
    $source = null;
    try {
        fwrite($stream, 'é🙂é*/tail');
        $source = new Utf8FileSource($stream, 4);
        $this->assertSame('é🙂é', C::readWhileSet('é🙂')->matchPrefix($source)->value());
        $this->assertSame('é🙂é', C::readUntil('*/')->matchPrefix($source)->value());
        $this->assertSame('é🙂é*/tail', C::readRest()->parse($source)->value());
    } finally {
        $source?->close();
        fclose($stream);
    }
});

test('scanner mappings stay cached and effects remain explicit and selected', function (): void {
    $mapped = 0;
    $committed = [];
    $parser = C::readUntil(';', min: 1)->mapValue(static function (ParseResult $result) use (&$mapped): string {
        ++$mapped;
        return strtoupper($result->rawText());
    })->onCommit(static function (ParseResult $result) use (&$committed): void {
        $committed[] = $result->rawText();
    });
    $result = $parser->parse('abc');
    $this->assertSame(0, $mapped);
    $this->assertSame([], $committed);
    $this->assertSame('ABC', $result->value());
    $this->assertSame('ABC', $result->value());
    $this->assertSame(1, $mapped);
    $this->assertTrue($result->commit());
    $this->assertTrue($result->commit());
    $this->assertSame(['abc'], $committed);
    $discarded = C::choice($parser->then('!'), C::text('abc'))->parse('abc');
    $this->assertTrue($discarded->commit());
    $this->assertSame(['abc'], $committed);
});

test('nullable scanner repetition is rejected during preparation', function (): void {
    $this->expectException(LogicException::class);
    C::readWhileSet('a')->many()->freeze();
});
