<?php

declare(strict_types=1);

namespace Tests\Feature\authoring;

use LogicException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Builder\FrozenGrammar;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Terminal\Text;

uses(TestCase::class);

test('owned facade snapshots are reused while recognition caches and effects remain separate', function () {
    $calls = 0;
    $effects = 0;
    $facade = C::text('a')->mapValue(function () use (&$calls) {
        return ++$calls;
    })->onCommit(function () use (&$effects) {
        ++$effects;
    });
    $this->assertSame($facade->freeze(), $facade->freeze());
    $first = $facade->parse('a');
    $second = $facade->parse('a');
    $this->assertSame(1, $first->value());
    $this->assertSame(1, $first->value());
    $this->assertSame(2, $second->value());
    $this->assertTrue($first->commit());
    $this->assertTrue($first->commit());
    $this->assertSame(1, $effects);
    $this->assertFalse($second->isCommitted());
    $this->assertFalse($facade->parse('b')->success);
    $this->assertTrue($facade->then('!')->parse('a!')->success);
    $this->assertTrue($facade->parse('a')->success);
});

test('deferred rules refresh after redefinition removal and factory state changes', function () {
    $rules = new Ruleset(['letter' => C::text('a')]);
    $facade = $rules->rule('letter');
    $frozen = $facade->freeze();
    $this->assertTrue($facade->parse('a')->success);
    $rules->define('letter', C::text('b'));
    $this->assertTrue($facade->parse('b')->success);
    $this->assertTrue($frozen->parse('a')->success);
    $text = 'c';
    $definition = C::of(new Definition(static function () use (&$text) {
        return new Text($text);
    }));
    $this->assertTrue($definition->parse('c')->success);
    $text = 'd';
    $this->assertTrue($definition->parse('d')->success);
    $rules->undefine('letter');
    $this->expectException(LogicException::class);
    $facade->parse('b');
});

test('custom freeze overrides cannot opt their caller owned state into facade reuse', function () {
    $service = (object) ['text' => 'a'];
    $parser = new class ('unused') extends Text {
        public object $service;

        public function freeze(): FrozenGrammar
        {
            return (new Text($this->service->text))->freeze();
        }
    };
    $parser->service = $service;
    $facade = C::of($parser);
    $this->assertTrue($facade->parse('a')->success);
    $service->text = 'b';
    $this->assertTrue($facade->parse('b')->success);
});

test('custom descendants keep fresh snapshots even below a built-in composite root', function () {
    $service = (object) ['text' => 'a'];
    $parser = new class ('unused') extends Text {
        public object $service;

        public function snapshot(GraphSnapshot $context): Parser
        {
            if (!$context->resolvesReferences()) {
                return parent::snapshot($context);
            }
            $copy = new Text($this->service->text);
            $context->registerCopy($this, $copy);

            return $copy;
        }
    };
    // Authoring copies must preserve the custom participant until freeze time.
    $parser->service = $service;
    $facade = C::sequence($parser, '!');
    $this->assertTrue($facade->parse('a!')->success);
    $service->text = 'b';
    $this->assertTrue($facade->parse('b!')->success);
});
