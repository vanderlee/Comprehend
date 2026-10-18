<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Builder\FreezeFlags;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;

test('resolved recursive references survive repeated snapshots and composition', function (int $flags): void {
    $rules = new Ruleset([], 'recursive');
    $rules->define('word', new Sequence(new Text('a'), Repeat::optional($rules->ref('word'))));
    $original = new Sequence(
        $rules->ref('word')->name('left', 'recursive'),
        new Text('|'),
        $rules->ref('word')->name('right', 'recursive'),
    );
    $resolved = (new GraphSnapshot())->copy($original);
    $repeated = (new GraphSnapshot())->copy($resolved);
    $children = $repeated->children();
    expect($children[0])->not->toBe($children[2]);
    expect($children[0]->children()[0])->toBe($children[2]->children()[0]);
    $other = new Ruleset(['word' => new Text('b')], 'other');
    $combined = new Sequence($repeated, new Text(':'), $other->ref('word')->name('suffix', 'other'));
    $frozen = $combined->freeze($flags);
    $rules->define('word', new Text('changed'));
    $other->define('word', new Text('changed'));
    $result = $frozen->parse('aaa|aa:b');

    expect($result->success)->toBeTrue();
    expect($result->ast()->allNamed('left', 'recursive'))->toHaveCount(1);
    expect($result->ast()->allNamed('right', 'recursive'))->toHaveCount(1);
    expect($result->ast()->firstNamed('suffix', 'other')->rawText())->toBe('b');
    expect($result->rawText())->toBe('aaa|aa:b');
})->with([FreezeFlags::NONE, FreezeFlags::DEFAULT]);

test('composed snapshots from different builder revisions keep their own productions', function (int $flags): void {
    $rules = new Ruleset(['item' => new Text('a')]);
    $before = (new GraphSnapshot())->copy($rules->ref('item')->name('before'));
    $rules->define('item', new Text('b'));
    $after = (new GraphSnapshot())->copy($rules->ref('item')->name('after'));
    $grammar = (new Sequence($before, $after))->freeze($flags);
    $rules->define('item', new Text('c'));

    $result = $grammar->parse('ab');
    expect($result->success)->toBeTrue();
    expect($result->ast()->firstNamed('before')->rawText())->toBe('a');
    expect($result->ast()->firstNamed('after')->rawText())->toBe('b');
    expect($grammar->parse('aa')->success)->toBeFalse();
    expect($grammar->parse('bb')->success)->toBeFalse();
})->with([FreezeFlags::NONE, FreezeFlags::DEFAULT]);

test('failed factory preparation does not publish partially resolved facade state', function (int $flags): void {
    $calls = [];
    $broken = true;
    $failure = new RuntimeException('Second factory failed');
    $rules = new Ruleset();
    $rules->define('first', static function () use (&$calls): Parser {
        $calls[] = 'first';

        return new Text('a');
    });
    $rules->define('second', static function () use (&$calls, &$broken, $failure): Parser {
        $calls[] = 'second';
        if ($broken) {
            throw $failure;
        }

        return new Text('b');
    });
    $facade = C::of(new Sequence($rules->ref('first'), $rules->ref('second')));
    for ($attempt = 0; $attempt < 2; ++$attempt) {
        $caught = null;
        try {
            $facade->freeze($flags);
        } catch (RuntimeException $error) {
            $caught = $error;
        }
        expect($caught)->toBe($failure);
    }
    expect($calls)->toBe(['first', 'second', 'first', 'second']);
    $broken = false;
    $frozen = $facade->freeze($flags);
    expect($calls)->toBe(['first', 'second', 'first', 'second', 'first', 'second']);
    expect($frozen->parse('ab')->success)->toBeTrue();
    expect($frozen->parse('aa')->success)->toBeFalse();
})->with([FreezeFlags::NONE, FreezeFlags::DEFAULT]);

test('preparation retries after a participant fails with already copied dependencies', function (int $flags): void {
    $state = (object) ['broken' => true, 'copies' => 0];
    $participant = new class ($state) extends Text {
        public function __construct(private readonly object $state)
        {
            parent::__construct('b');
        }

        public function snapshot(GraphSnapshot $context): Parser
        {
            $copy = parent::snapshot($context);
            ++$this->state->copies;
            if ($context->resolvesReferences() && $this->state->broken) {
                throw new RuntimeException('Registered participant failed');
            }

            return $copy;
        }
    };
    $first = new Text('a');
    $facade = C::of(new Sequence($first, $participant));
    $copiesBeforeFailure = $state->copies;
    expect(static fn() => $facade->freeze($flags))->toThrow(RuntimeException::class, 'Registered participant failed');
    expect($state->copies)->toBeGreaterThan($copiesBeforeFailure);
    $copiesAfterFailure = $state->copies;
    $state->broken = false;
    $frozen = $facade->freeze($flags);
    expect($state->copies)->toBeGreaterThan($copiesAfterFailure);
    $first->guard(static fn(): bool => false);
    expect($frozen->parse('ab')->success)->toBeTrue();
    expect($frozen->parse('aa')->success)->toBeFalse();
})->with([FreezeFlags::NONE, FreezeFlags::DEFAULT]);
