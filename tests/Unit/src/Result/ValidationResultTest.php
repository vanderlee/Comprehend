<?php

declare(strict_types=1);

namespace Tests\Src\Result;

use Error;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Result\ValidationIssue;
use Vanderlee\Comprehend\Result\ValidationResult;
use Vanderlee\Comprehend\Source\ByteSource;

uses(TestCase::class);

test('validation issues retain immutable snapshots and serialize byte coordinates', function (): void {
    $value = 7;
    $context = ['nested' => ['value' => &$value]];
    $issue = new ValidationIssue('range', 'Outside range', (new ByteSource('abc'))->span(1, 3), $context);
    $issues = [&$issue];
    $result = new ValidationResult($issues);
    $retained = $issue;
    $value = 9;
    $issue = new ValidationIssue('other', 'Other', $issue->span);
    $this->assertFalse($result->valid);
    $this->assertSame([$retained], $result->issues);
    $this->assertSame(['nested' => ['value' => 7]], $retained->context);
    $expected = ['code' => 'range', 'message' => 'Outside range',
        'span' => ['start' => 1, 'end' => 3], 'context' => ['nested' => ['value' => 7]]];
    $this->assertSame($expected, $retained->toArray());
    $this->assertSame($expected, json_decode($retained->toJson(), true, flags: JSON_THROW_ON_ERROR));
    $this->assertSame(['valid' => false, 'issues' => [$expected]], $result->toArray());
    $this->assertSame($result->toJson(), json_encode($result, JSON_THROW_ON_ERROR));
    $this->assertTrue((new ValidationResult())->valid);
    $this->expectException(Error::class);
    $result->issues[] = $retained;
});

test('validation results reject malformed issue lists', function (array $issues): void {
    $this->expectException(InvalidArgumentException::class);
    new ValidationResult($issues);
})->with([[['bad']], [['key' => 'bad']]]);

test('validation context rejects non JSON data', function (mixed $value): void {
    $this->expectException(InvalidArgumentException::class);
    new ValidationIssue('invalid', 'Invalid', (new ByteSource(''))->span(0, 0), ['value' => $value]);
})->with([new \stdClass(), INF, NAN, "\xff"]);

test('validation context rejects recursive arrays', function (): void {
    $context = [];
    $context['self'] = &$context;
    $this->expectException(InvalidArgumentException::class);
    new ValidationIssue('invalid', 'Invalid', (new ByteSource(''))->span(0, 0), $context);
});

test('validation codes must be nonblank', function (): void {
    $this->expectException(InvalidArgumentException::class);
    new ValidationIssue(' ', 'Invalid', (new ByteSource(''))->span(0, 0));
});
