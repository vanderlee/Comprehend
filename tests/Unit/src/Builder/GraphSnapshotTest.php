<?php

declare(strict_types=1);

namespace Tests\Src\Builder;

use PHPUnit\Framework\TestCase;
use LogicException;
use RuntimeException;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Builder\Implementation;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\All;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;

uses(TestCase::class);

test('snapshot reuse excludes deferred definitions and custom participants', function () {
    $owned = new GraphSnapshot();
    $owned->copy(new Sequence(new Text('a'), new Text('b')));
    $this->assertTrue($owned->isReusable());
    $deferred = new GraphSnapshot();
    $deferred->copy(new Implementation(new Definition(new Text('a'))));
    $this->assertFalse($deferred->isReusable());
    $custom = new GraphSnapshot();
    $custom->copy(new class ('a') extends Text {
        public function __toString(): string
        {
            return 'custom text';
        }
    });
    $this->assertFalse($custom->isReusable());
});

test('value copies preserve sharing and caller owned dependencies', function (): void {
    $parser = new Text('a');
    $definition = new Definition($parser);
    $service = new \stdClass();
    $copy = (new GraphSnapshot())->copyValue([$parser, ['definition' => $definition], $service]);

    $this->assertNotSame($parser, $copy[0]);
    $this->assertNotSame($definition, $copy[1]['definition']);
    $this->assertSame($copy[0], $copy[1]['definition']->generator);
    $this->assertSame($service, $copy[2]);
});

test('copy registration rejects changing an existing destination', function (): void {
    $parser = new Text('a');
    $context = new GraphSnapshot();
    $context->copy($parser);
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('already has a different copy');

    $context->registerCopy($parser, new Text('b'));
});

test('copy registration preserves object category', function (): void {
    $parser = new Text('a');
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('must preserve the parser or definition category');

    (new GraphSnapshot())->registerCopy($parser, new Definition($parser));
});

test('copy registration rejects retaining the original', function (): void {
    $parser = new Text('a');
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('must be detached from its original');

    (new GraphSnapshot())->registerCopy($parser, $parser);
});

test('copying owned state does not execute semantic callbacks', function (): void {
    $calls = 0;
    $child = (new Text('a'))->mapValue(static function () use (&$calls): int {
        return ++$calls;
    });
    $copy = (new GraphSnapshot())->copy(new Sequence($child));

    $this->assertCount(1, $copy->children());
    $this->assertNotSame($child, $copy->children()[0]);
    $this->assertSame(0, $calls);
});

test('shared definitions remain definitions when copied from cache', function (): void {
    $definition = new Definition(new Text('a'));
    $original = new Sequence(new Implementation($definition), new Implementation($definition));
    $copy = (new GraphSnapshot())->copy($original);

    $this->assertTrue($copy->parseAll(new ByteSource('aa'))->success);
});

test('named all operands can be analyzed and frozen', function (): void {
    $grammar = (new All(first: new Text('a'), second: new Text('a')))->freeze();

    $this->assertTrue($grammar->parseAll(new ByteSource('a'))->success);
});

test('copies detach owned children but preserve sharing', function (): void {
    $child = new Text('a');
    $original = new Sequence($child, $child);
    $copy = (new GraphSnapshot())->copy($original);
    $children = iterator_to_array($copy);
    $child->guard(static fn(): bool => false);

    $this->assertNotSame($original, $copy);
    $this->assertNotSame($child, $children[0]);
    $this->assertSame($children[0], $children[1]);
    $this->assertTrue($copy->parseAll(new ByteSource('aa'))->success);
    $this->assertFalse($original->parseAll(new ByteSource('aa'))->success);
});

test('failed participant can retry when it has not registered a copy', function (): void {
    $parser = new class ('a') extends Text {
        private bool $failNextSnapshot = true;

        public function snapshot(GraphSnapshot $context): Parser
        {
            if ($this->failNextSnapshot) {
                $this->failNextSnapshot = false;
                throw new RuntimeException('Snapshot preparation failed');
            }

            return parent::snapshot($context);
        }
    };
    $context = new GraphSnapshot();

    try {
        $context->copy($parser);
        $this->fail('Expected snapshot preparation failure');
    } catch (RuntimeException $error) {
        $this->assertSame('Snapshot preparation failed', $error->getMessage());
    }

    $copy = $context->copy($parser);
    $this->assertNotSame($parser, $copy);
    $this->assertSame($copy, $context->copy($parser));
    $this->assertTrue($copy->parseAll(new ByteSource('a'))->success);
});
