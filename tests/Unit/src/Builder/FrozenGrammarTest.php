<?php

declare(strict_types=1);

namespace Tests\Src\Builder;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vanderlee\Comprehend\Builder\FreezeFlags;
use Vanderlee\Comprehend\Builder\FrozenGrammar;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;

uses(TestCase::class);

test('snapshot is independent of later parser mutations', function (): void {
    $parser = new Text('a');
    $frozen = FrozenGrammar::from($parser);
    $parser->guard(static fn(): bool => false);

    $this->assertTrue($frozen->parse('a')->success);
    $this->assertSame([], $frozen->analysisWarnings);
    $this->assertSame('a', $frozen->matchPrefix('xab', 1)->rawText());
    $this->assertFalse($frozen->parseAll('xab', 1)->success);
});

test('only a built-in source can reuse a snapshot of an entirely built-in graph', function () {
    $parser = new Text('a');
    $frozen = FrozenGrammar::from($parser);
    $this->assertTrue($frozen->canReuseFor($parser));
    $this->assertFalse($frozen->canReuseFor(new class ('a') extends Text {
        public function __toString(): string
        {
            return 'custom text';
        }
    }));
});

test('facade freezing copies shared participants directly into the destination snapshot', function (int $flags): void {
    $state = (object) ['copies' => []];
    $shared = new class ($state) extends Text {
        public function __construct(private readonly object $state)
        {
            parent::__construct('a');
        }

        public function snapshot(GraphSnapshot $context): Parser
        {
            $this->state->copies[] = [$context->resolvesReferences(), $context->optimizationEnabled()];

            return parent::snapshot($context);
        }
    };
    $facade = C::of(new Sequence($shared, $shared));
    $state->copies = [];
    $grammar = FrozenGrammar::from($facade, $flags);
    $this->assertSame([[true, $flags === FreezeFlags::OPTIMIZE]], $state->copies);
    $this->assertTrue($grammar->parse('aa')->success);

    $exported = $facade->parser();
    $this->assertSame([false, false], $state->copies[1]);
    $this->assertSame($exported->children()[0], $exported->children()[1]);
    $exported->children()[0]->guard(static fn(): bool => false);
    $this->assertTrue($grammar->parse('aa')->success);
    $this->assertTrue(FrozenGrammar::from($facade, $flags)->parse('aa')->success);
})->with([FreezeFlags::NONE, FreezeFlags::OPTIMIZE]);

test('direct facade snapshots keep factory creation distinct from custom freeze dispatch', function (int $flags): void {
    $state = (object) ['flags' => []];
    $parser = new class ($state) extends Text {
        public function __construct(private readonly object $state)
        {
            parent::__construct('original');
        }

        public function freeze(int $flags = FreezeFlags::DEFAULT): FrozenGrammar
        {
            $this->state->flags[] = $flags;

            return FrozenGrammar::from(new Text('override'), $flags);
        }
    };
    $facade = C::of($parser);
    $first = FrozenGrammar::from($facade, $flags);
    $second = FrozenGrammar::from($facade, $flags);
    $this->assertNotSame($first, $second);
    $this->assertTrue($first->parse('original')->success);
    $this->assertSame([], $state->flags);
    $this->assertTrue($facade->freeze($flags)->parse('override')->success);
    $this->assertSame([$flags], $state->flags);
})->with([FreezeFlags::NONE, FreezeFlags::OPTIMIZE]);

test('direct facade snapshots refresh factories once per production and retain earlier definitions', function (
    int $flags,
): void {
    $text = 'a';
    $calls = 0;
    $rules = new Ruleset();
    $rules->define('word', static function () use (&$text, &$calls): Parser {
        ++$calls;

        return new Text($text);
    });
    $facade = C::of(new Sequence($rules->ref('word'), $rules->ref('word')));
    $first = FrozenGrammar::from($facade, $flags);
    $this->assertSame(1, $calls);
    $text = 'b';
    $second = FrozenGrammar::from($facade, $flags);
    $this->assertSame(2, $calls);
    $this->assertTrue($first->parse('aa')->success);
    $this->assertTrue($second->parse('bb')->success);
    $rules->define('word', new Text('c'));
    $this->assertTrue(FrozenGrammar::from($facade, $flags)->parse('cc')->success);
    $this->assertTrue($second->parse('bb')->success);
})->with([FreezeFlags::NONE, FreezeFlags::OPTIMIZE]);

test('failed direct facade snapshots do not publish partially copied graphs', function (int $flags): void {
    $failure = new RuntimeException('Snapshot failed');
    $state = (object) ['broken' => true];
    $parser = new class ($state, $failure) extends Text {
        public function __construct(private readonly object $state, private readonly RuntimeException $failure)
        {
            parent::__construct('a');
        }

        public function snapshot(GraphSnapshot $context): Parser
        {
            $copy = parent::snapshot($context);
            if ($context->resolvesReferences() && $this->state->broken) {
                throw $this->failure;
            }

            return $copy;
        }
    };
    $facade = C::of(new Sequence(new Text('!'), $parser));
    for ($attempt = 0; $attempt < 2; ++$attempt) {
        try {
            FrozenGrammar::from($facade, $flags);
            $this->fail('Expected a snapshot failure');
        } catch (RuntimeException $error) {
            $this->assertSame($failure, $error);
        }
    }
    $state->broken = false;
    $this->assertTrue(FrozenGrammar::from($facade, $flags)->parse('!a')->success);
})->with([FreezeFlags::NONE, FreezeFlags::OPTIMIZE]);

test('direct facade snapshots retain byte spans and independent lazy values and effects', function (int $flags): void {
    $maps = 0;
    $effects = 0;
    $facade = C::sequence('é', '!')->mapValue(static function (ParseResult $result) use (&$maps): string {
        ++$maps;

        return $result->rawText();
    })->onCommit(static function () use (&$effects): void {
        ++$effects;
    });
    $grammar = FrozenGrammar::from($facade, $flags);
    $first = $grammar->parseAll('xé!', 1);
    $second = $grammar->parseAll('xé!', 1);
    $this->assertSame(0, $maps);
    $this->assertSame(0, $effects);
    $this->assertSame(1, $first->start);
    $this->assertSame(4, $first->end);
    $this->assertSame('é!', $first->value());
    $this->assertSame('é!', $first->value());
    $this->assertSame(1, $maps);
    $this->assertSame('é!', $second->value());
    $this->assertSame(2, $maps);
    $this->assertTrue($first->commit());
    $this->assertTrue($first->commit());
    $this->assertSame(1, $effects);
    $this->assertFalse($second->isCommitted());
    $this->assertFalse($grammar->parseAll('xé?', 1)->success);
})->with([FreezeFlags::NONE, FreezeFlags::OPTIMIZE]);
