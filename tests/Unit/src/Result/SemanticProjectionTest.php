<?php

declare(strict_types=1);

namespace Tests\Src\Result;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use LogicException;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\SemanticProjection;
use Vanderlee\Comprehend\Source\ByteSource;

uses(TestCase::class);

test('null mapping results are cached', function (): void {
    $calls = 0;
    $projection = new SemanticProjection(static function () use (&$calls): mixed {
        ++$calls;

        return null;
    });
    $result = (new Text('a'))->parseAll(new ByteSource('a'));

    $this->assertNull($projection->value($result));
    $this->assertNull($projection->value($result));
    $this->assertSame(1, $calls);
    $this->assertSame('a', (new SemanticProjection(null))->value($result));
});

test('mapping failures are retained without retrying', function (): void {
    $calls = 0;
    $error = new RuntimeException('mapping failed');
    $projection = new SemanticProjection(static function () use (&$calls, $error): never {
        ++$calls;
        throw $error;
    });
    $result = (new Text('a'))->parseAll(new ByteSource('a'));

    for ($attempt = 0; $attempt < 2; ++$attempt) {
        try {
            $projection->value($result);
            $this->fail('Expected the retained mapping failure');
        } catch (RuntimeException $caught) {
            $this->assertSame($error, $caught);
        }
    }
    $this->assertSame(1, $calls);
});

test('execution records and public results share one cached semantic result', function (): void {
    $match = new Success(1);
    $match->setSourceMetadata((new ByteSource('a'))->span(0, 1), null);
    $projection = new SemanticProjection(static fn(ParseResult $result): ParseResult => $result);
    $value = $projection->value($match);

    $this->assertSame('a', $value->rawText());
    $this->assertSame($value, $projection->value($match));
    $this->assertSame($value, $projection->value(new ParseResult($match)));
});

test('record-based semantic evaluation caches nulls and the original recursive failure', function (): void {
    $match = new Success(1);
    $match->setSourceMetadata((new ByteSource('a'))->span(0, 1), null);
    $calls = 0;
    $match->setValueMapper(static function () use (&$calls): mixed {
        ++$calls;
        return null;
    });
    $this->assertNull($match->getValue());
    $this->assertNull($match->getValue());
    $this->assertSame(1, $calls);

    $match->setValueMapper(static fn(ParseResult $result): mixed => $result->value());
    $failure = null;
    for ($attempt = 0; $attempt < 2; ++$attempt) {
        try {
            $match->getValue();
            $this->fail('Expected recursive value evaluation to fail');
        } catch (LogicException $error) {
            $failure ??= $error;
            $this->assertSame('Recursive semantic value evaluation', $error->getMessage());
            $this->assertSame($failure, $error);
        }
    }
});
