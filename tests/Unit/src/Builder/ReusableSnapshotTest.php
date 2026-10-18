<?php

declare(strict_types=1);

namespace Tests\Src\Builder;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Builder\ReusableSnapshot;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Structure\ValueSequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;

uses(TestCase::class);

test('an inherited capability interface does not opt a subclass into reuse', function (): void {
    $subclass = new class ('a') extends Text {
        // Represents a custom subclass that has not declared its own reuse guarantee.
    };
    $this->assertInstanceOf(ReusableSnapshot::class, $subclass);
    $this->assertFalse($subclass->supportsSnapshotReuse());
    $facade = C::of($subclass);
    $this->assertNotSame($facade->freeze(), $facade->freeze());
});

test('custom parsers can explicitly opt in without central registration', function (): void {
    $calls = 0;
    $effects = 0;
    $parser = new class ('a') extends Text {
        public function supportsSnapshotReuse(): bool
        {
            return static::class === self::class;
        }
    };
    $facade = C::of($parser)->mapValue(static function () use (&$calls): int {
        return ++$calls;
    })->onCommit(static function () use (&$effects): void {
        ++$effects;
    });
    $this->assertSame($facade->freeze(), $facade->freeze());
    $first = $facade->parse('a');
    $second = $facade->parse('a');
    $this->assertSame(0, $calls);
    $this->assertSame(1, $first->value());
    $this->assertSame(1, $first->value());
    $this->assertSame(2, $second->value());
    $this->assertTrue($first->commit());
    $this->assertTrue($first->commit());
    $this->assertSame(1, $effects);
    $this->assertFalse($second->isCommitted());
    $this->assertFalse($facade->parse('b')->success);
});

test('an inherited capability can be explicitly declined', function (): void {
    $parser = new class ('a') extends Text {
        public function supportsSnapshotReuse(): bool
        {
            return false;
        }
    };
    $this->assertFalse(GraphSnapshot::isReusableParser($parser));
});

test('capable composites still require every copied operand to qualify', function (): void {
    $safe = C::sequence(new class ('a') extends Text {
        public function supportsSnapshotReuse(): bool
        {
            return static::class === self::class;
        }
    }, '!');
    $this->assertSame($safe->freeze(), $safe->freeze());
    $unsafe = C::sequence(new class ('a') extends Text {
        // No reuse guarantee for this custom child.
    }, '!');
    $this->assertNotSame($unsafe->freeze(), $unsafe->freeze());
    $this->assertTrue($unsafe->parse('a!')->success);
});

test('built in value sequence declares its own capability', function (): void {
    $parser = new ValueSequence(0, new Text('a'), new Text('!'));
    $this->assertTrue($parser->supportsSnapshotReuse());
    $facade = C::of($parser);
    $this->assertSame($facade->freeze(), $facade->freeze());
    $this->assertSame('a', $facade->parse('a!')->value());
    $subclass = new class (0, new Text('a')) extends ValueSequence {
        // The built-in ValueSequence guarantee does not extend to subclasses.
    };
    $this->assertFalse($subclass->supportsSnapshotReuse());
});

test('non reusable spacing also prevents composite preparation reuse', function (): void {
    $parser = (new Sequence(new Text('a'), new Text('b')))->spacing(new class (' ') extends Text {
        // Spacing participates in the reuse decision just like recognition operands.
    });
    $facade = C::of($parser);
    $this->assertNotSame($facade->freeze(), $facade->freeze());
    $this->assertTrue($facade->parse('a b')->success);
});
