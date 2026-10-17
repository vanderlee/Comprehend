<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Directive\Lexeme;
use Vanderlee\Comprehend\Parser\Structure\ValueSequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Source;

uses(TestCase::class);

test('nested selected values keep child spans and observable commit state', function (): void {
    $calls = 0;
    $item = (new Text('x'))->mapValue(static function (ParseResult $result) use (&$calls): ParseResult {
        ++$calls;

        return $result;
    });
    $inner = new ValueSequence(1, new Text('('), $item, new Text(')'));
    $outer = new ValueSequence(1, new Text('['), $inner, new Text(']'));
    $result = $outer->parseAll(new ByteSource('[(x)]'));
    $this->assertSame(0, $calls);
    $selected = $result->value();
    $this->assertSame([2, 3, 'x'], [$selected->start, $selected->end, $selected->rawText()]);
    $this->assertSame('[(x)]', $result->rawText());
    $this->assertSame($selected, $result->value());
    $this->assertSame(1, $calls);
    $this->assertFalse($selected->isCommitted());
    $this->assertTrue($result->commit());
    $this->assertTrue($selected->isCommitted());
    $this->assertCount(3, $result->node->children);
    $this->assertCount(3, $result->node->children[1]->children);
});

test('selected raw text and subsequent transformations exclude delimiters', function (): void {
    $sequence = new ValueSequence(1, new Text('('), new Text('x'), new Text(')'));
    $this->assertSame('x', $sequence->parseAll(new ByteSource('(x)'))->value());
    $calls = 0;
    $sequence->map(static function (string $value) use (&$calls): string {
        ++$calls;

        return strtoupper($value);
    });
    $result = $sequence->parseAll(new ByteSource('(x)'));
    $this->assertSame('X', $result->value());
    $this->assertSame('X', $result->value());
    $this->assertSame(1, $calls);
    $sequence->mapValue(static fn(ParseResult $result): string => $result->rawText());
    $this->assertSame('(x)', $sequence->parseAll(new ByteSource('(x)'))->value());
});

test('selected value errors remain lazy and retain exception identity', function (): void {
    $calls = 0;
    $error = new RuntimeException('selected value failed');
    $item = (new Text('x'))->mapValue(static function () use (&$calls, $error): never {
        ++$calls;
        throw $error;
    });
    $sequence = new ValueSequence(1, new Text('('), $item, new Text(')'));
    $result = $sequence->parseAll(new ByteSource('(x)'));
    $this->assertSame(0, $calls);
    for ($i = 0; $i < 2; ++$i) {
        try {
            $result->value();
            $this->fail('Expected the selected value error');
        } catch (RuntimeException $caught) {
            $this->assertSame($error, $caught);
        }
    }
    $this->assertSame(1, $calls);
    $sequence->mapValue(static fn(): int => 42);
    $this->assertSame(42, $sequence->parseAll(new ByteSource('(x)'))->value());
    $this->assertSame(1, $calls);
});

test('custom selected getters are still evaluated once by their sequence', function (bool $wrapped): void {
    $item = new class ('x') extends Text {
        public ?Success $last = null;

        protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
        {
            $this->last = new class (1) extends Success {
                public int $reads = 0;

                public function getValue(): mixed
                {
                    return (object) ['read' => ++$this->reads];
                }
            };
            $this->last->setValueMapper(static fn(): mixed => null);

            return $this->last;
        }
    };
    $sequence = new ValueSequence(1, new Text('('), $wrapped ? new Lexeme($item) : $item, new Text(')'));
    $result = $sequence->parseAll(new ByteSource('(x)'));
    $this->assertSame(0, $item->last->reads);
    $value = $result->value();
    $this->assertSame($value, $result->value());
    $this->assertSame(1, $value->read);
    $this->assertSame(1, $item->last->reads);
})->with([false, true]);

test('custom sequence records retain their mapping registration behavior', function (): void {
    $sequence = new class (1, new Text('('), new Text('x'), new Text(')')) extends ValueSequence {
        protected function success(
            Source $source,
            int $offset,
            int $length = 0,
            array|Success $children = [],
        ): Success {
            $match = new class ($length, is_array($children) ? $children : [$children]) extends Success {
                public function setValueMapper(?callable $mapper): void
                {
                    parent::setValueMapper($mapper === null ? null
                        : static fn(ParseResult $result): string => 'custom:' . $mapper($result));
                }
            };
            $match->setSourceMetadata($source->span($offset, $offset + $length), null);

            return $match;
        }
    };

    $this->assertSame('custom:x', $sequence->parseAll(new ByteSource('(x)'))->value());
});
