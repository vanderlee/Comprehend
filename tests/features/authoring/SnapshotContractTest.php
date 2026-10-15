<?php

declare(strict_types=1);

namespace Tests\features\authoring;

use LogicException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Builder\Implementation;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Builder\SnapshotParticipant;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Stub;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;

uses(TestCase::class);

test('custom participant preserves recursive sharing and isolation', function (): void {
    $parser = new class extends Stub implements SnapshotParticipant {
        public function snapshot(GraphSnapshot $context): Parser
        {
            $copy = clone $this;
            $context->registerCopy($this, $copy);
            $child = $this->getParser();
            if ($child !== null) {
                $copy->setParser($context->copy($child));
            }

            return $copy;
        }
    };
    $parser->setParser(new Sequence(new Text('a'), Repeat::optional($parser)));
    $context = new GraphSnapshot();
    $copy = $context->copy($parser);
    $repeat = $copy->children()[0]->children()[1];
    $parser->setParser(new Text('b'));

    $this->assertNotSame($parser, $copy);
    $this->assertSame($copy, $context->copy($parser));
    $this->assertSame([$copy], $repeat->children());
    $this->assertTrue($copy->parseAll(new ByteSource('aaa'))->success);
    $this->assertFalse($copy->parseAll(new ByteSource('b'))->success);
    $this->assertTrue($parser->parseAll(new ByteSource('b'))->success);
});

test('unregistered recursive participant fails instead of recursing forever', function (): void {
    $parser = new class ('a') extends Text implements SnapshotParticipant {
        public function snapshot(GraphSnapshot $context): Parser
        {
            return $context->copy($this);
        }
    };
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('must register its copy before copying dependencies');

    (new GraphSnapshot())->copy($parser);
});

test('participant must return its registered destination', function (): void {
    $parser = new class ('a') extends Text implements SnapshotParticipant {
        public function snapshot(GraphSnapshot $context): Parser
        {
            return clone parent::snapshot($context);
        }
    };
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('must return its registered copy');

    (new GraphSnapshot())->copy($parser);
});

test('unregistered non recursive participant is rejected', function (): void {
    $parser = new class ('a') extends Text implements SnapshotParticipant {
        public function snapshot(GraphSnapshot $context): Parser
        {
            return clone $this;
        }
    };
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('must return its registered copy');

    (new GraphSnapshot())->copy($parser);
});

test('implementation refreshes cached factory only in resolving copies', function (): void {
    $text = 'a';
    $calls = 0;
    $definition = new Definition(static function () use (&$text, &$calls): Parser {
        ++$calls;

        return new Text($text);
    });
    $implementation = new Implementation($definition);
    $implementation->graphTarget();
    $text = 'b';
    $unresolved = (new GraphSnapshot(false))->copy($implementation);
    $this->assertSame(1, $calls);
    $this->assertTrue($unresolved->parseAll(new ByteSource('a'))->success);

    $resolved = (new GraphSnapshot())->copy($implementation);
    $this->assertSame(2, $calls);
    $this->assertTrue($resolved->parseAll(new ByteSource('b'))->success);
    $this->assertFalse($resolved->parseAll(new ByteSource('a'))->success);
    $this->assertTrue($implementation->parseAll(new ByteSource('a'))->success);
});

test('non resolving reference copy remains deferred and tracks builder', function (): void {
    $calls = 0;
    $rules = new Ruleset();
    $rules->define('word', static function () use (&$calls): Parser {
        ++$calls;

        return new Text('a');
    });
    $copy = (new GraphSnapshot(false))->copy($rules->ref('word'));
    $this->assertSame(0, $calls);
    $rules->define('word', new Text('b'));

    $this->assertTrue($copy->parseAll(new ByteSource('b'))->success);
    $this->assertFalse($copy->parseAll(new ByteSource('a'))->success);
    $this->assertSame(0, $calls);
});

test('resolving ruleset prunes unused factories and remains detached', function (): void {
    $rules = new Ruleset();
    $rules->define('used', new Text('a'))->root('used');
    $rules->define('unused', static function (): never {
        throw new LogicException('Unused factory must not run');
    });
    $copy = (new GraphSnapshot())->copy($rules);
    $rules->define('used', new Text('b'));

    $this->assertInstanceOf(Ruleset::class, $copy);
    $this->assertFalse($copy->defined('unused'));
    $this->assertTrue($copy->parseAll(new ByteSource('a'))->success);
    $this->assertTrue($rules->parseAll(new ByteSource('b'))->success);
});

test('equivalent object arguments reuse references without merging distinct objects', function (): void {
    $calls = 0;
    $first = (object) ['text' => 'a'];
    $second = (object) ['text' => 'a'];
    $rules = new Ruleset();
    $rules->define('word', static function (object $argument) use (&$calls): Parser {
        ++$calls;

        return new Text($argument->text);
    });
    $parser = new Sequence(
        $rules->ref('word', $first),
        $rules->ref('word', $second),
        $rules->ref('word', $first),
    );
    $copy = (new GraphSnapshot())->copy($parser);
    $children = $copy->children();

    $this->assertSame(2, $calls);
    $this->assertSame($children[0], $children[2]);
    $this->assertNotSame($children[0], $children[1]);
    $this->assertTrue($copy->parseAll(new ByteSource('aaa'))->success);
});
