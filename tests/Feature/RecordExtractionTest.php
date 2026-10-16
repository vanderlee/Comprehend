<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Extraction;
use Vanderlee\Comprehend\Extensions\Extraction\Parser\Record;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Source\ByteSource;

uses(TestCase::class);

test('records preserve ordered keys typed values and trivia round trips', function (): void {
    $s = Extraction::syntax();
    $parser = $s->record([
        'name' => $s->readWhileSet('abc', min: 1),
        7 => $s->integer()->mapText('intval'),
        'absent' => $s->text('?')->optionalValue(),
        'disabled' => $s->text('off')->map(static fn(string $text): bool => false),
    ])->spacing(C::readWhileSet(' '))->named('record');
    $result = $parser->freeze()->parse('abc 12 off');
    $this->assertTrue($result->success);
    $this->assertSame(['name' => 'abc', 7 => 12, 'absent' => null, 'disabled' => false], $result->value());
    $this->assertSame('abc 12 off', $result->rawText());
    $this->assertSame('abc 12 off', $result->node->rawText());
    $this->assertNotNull($result->ast());
});

test('records nest and retain named factory arguments', function (): void {
    $s = Extraction::syntax();
    $inner = $s->record(['count' => $s->integer()->mapText('intval')]);
    $parser = $s->record(fields: ['label' => 'x', 'data' => $inner->between('[', ']')]);
    $this->assertSame(['label' => 'x', 'data' => ['count' => 2]], $parser->parse('x[2]')->value());
    $this->assertSame('xy', $s->text('x')->record(['field' => 'y'])->parse('xy')->value());
});

test('empty records recognize empty input and expose empty values', function (): void {
    $parser = Extraction::syntax()->record([])->freeze();
    $result = $parser->parse('');
    $this->assertTrue($result->success);
    $this->assertSame([], $result->value());
    $this->assertSame([], $result->node->children);
    $this->assertFalse($parser->parse('x')->success);
});

test('record failures preserve child progress and byte diagnostics', function (): void {
    $result = Extraction::syntax()->record(['first' => 'é', 'second' => 'ok'])->parse('éo?');
    $this->assertFalse($result->success);
    $this->assertSame(0, $result->consumedByteLength);
    $this->assertSame(3, $result->farthestFailure);
    $this->assertNull($result->value());
});

test('record projection is lazy cached and independent of explicit effects', function (): void {
    $s = Extraction::syntax();
    $mapped = 0;
    $effects = [];
    $field = $s->text('x')->map(static function (string $value) use (&$mapped): string {
        ++$mapped;
        return strtoupper($value);
    })->onCommit(static function (ParseResult $result) use (&$effects): void {
        $effects[] = $result->rawText();
    });
    $record = $s->record(['field' => $field]);
    $result = $record->parse('x');
    $this->assertSame(0, $mapped);
    $this->assertSame([], $effects);
    $this->assertSame(['field' => 'X'], $result->value());
    $this->assertSame(['field' => 'X'], $result->value());
    $this->assertSame(1, $mapped);
    $this->assertTrue($result->commit());
    $this->assertTrue($result->commit());
    $this->assertSame(['x'], $effects);
    $discarded = C::choice($record->then('!'), C::text('x'))->parse('x');
    $this->assertTrue($discarded->commit());
    $this->assertSame(1, $mapped);
    $this->assertSame(['x'], $effects);
});

test('record snapshots retain field names independently of mutable builders', function (): void {
    $record = new Record(['before' => new Text('a')]);
    $frozen = C::of($record)->freeze();
    $retained = $record->parseAll(new ByteSource('a'));
    unset($record['before']);
    $record['after'] = new Text('b');
    $this->assertSame(['before' => 'a'], $retained->value());
    $this->assertSame(['before' => 'a'], $frozen->parse('a')->value());
    $this->assertSame(['after' => 'b'], $record->parseAll(new ByteSource('b'))->value());
});

test('record constructors require explicit parser fields', function (): void {
    $this->expectException(TypeError::class);
    new Record(['invalid' => 'literal']);
});

test('appending record operands preserves existing integer field keys', function (): void {
    $record = (new Record([7 => new Text('a')]))->add(new Text('b'));
    $this->assertSame([7 => 'a', 8 => 'b'], $record->parseAll(new ByteSource('ab'))->value());
});

test('empty record repetition requires a progress-making operand', function (): void {
    $this->expectException(LogicException::class);
    Extraction::syntax()->record([])->manyValues()->freeze();
});
