<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Builder\FrozenGrammar;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Not;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\Diagnostic;
use Vanderlee\Comprehend\Runtime\ChoicePolicy;
use Vanderlee\Comprehend\Runtime\DeferredDiagnostic;
use Vanderlee\Comprehend\Runtime\MemoizationProfile;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Source;

test('nested repeated work has a nonoverlapping total rather than summed inclusive cost', function () {
    $probe = new Not(new Text(str_repeat('a', 64) . '!'));
    foreach ([0, 1] as $flags) {
        $grammar = (new Sequence($probe, $probe, new Text(str_repeat('a', 64) . 'y')))->freeze($flags);
        $profile = new MemoizationProfile();
        expect($grammar->parse(str_repeat('a', 64) . 'y', memoizationProfile: $profile)->success)->toBeTrue();
        $counts = $profile->snapshot();
        expect($counts['entries'])->toBe(6)->and($counts['position_repeats'])->toBe(2)
            ->and($counts['policy_repeats'])->toBe(2)->and($counts['strict_repeats'])->toBe(2)
            ->and($counts['strict_repeated_work_inclusive'])->toBe(133)
            ->and($counts['strict_repeated_work_nonoverlap'])->toBe(67)
            ->and($counts['summary_conflicts'])->toBe(0)->and($counts['active_traces'])->toBe(0);
    }
});

test('policies caller diagnostics and retention requirements split recognition contexts', function () {
    $grammar = (new Text('a'))->freeze(0);
    $root = (new ReflectionProperty(FrozenGrammar::class, 'root'))->getValue($grammar);
    $profile = new MemoizationProfile();
    $profile->registerFrozenGraph($root);
    $source = new ByteSource('a');
    $session = new ParseSession($source, memoizationProfile: $profile);
    $root->recognizeInSession($source, 0, $session);
    $session->pushCaseSensitivity(false);
    $root->recognizeInSession($source, 0, $session);
    $session->popCaseSensitivity();
    $session->pushPreference(ChoicePolicy::LONGEST);
    $root->recognizeInSession($source, 0, $session);
    $session->popPreference();
    $session->pushLexeme();
    $root->recognizeInSession($source, 0, $session);
    $session->popLexeme();
    $root->recognizeInSession($source, 0, $session, retainFailureDiagnostic: false);
    expect($profile->snapshot()['policy_repeats'])->toBe(0);
    $session->pushRule('another-caller');
    $root->recognizeInSession($source, 0, $session);
    $session->popRule();
    expect($profile->snapshot()['policy_repeats'])->toBe(1)
        ->and($profile->snapshot()['strict_repeats'])->toBe(0);
    $session->recordFailure(0, 'inherited');
    $root->recognizeInSession($source, 0, $session);
    expect($profile->snapshot()['policy_summary_conflicts'])->toBe(1)
        ->and($profile->snapshot()['strict_repeats'])->toBe(0);
});

test('sessions and source lifetimes never alias or stay alive through a profile', function () {
    $grammar = (new Text('a'))->freeze();
    $profile = new MemoizationProfile();
    $source = new ByteSource('a');
    $reference = WeakReference::create($source);
    for ($i = 0; $i < 3; ++$i) {
        $result = $grammar->matchPrefix($source, memoizationProfile: $profile);
    }
    unset($result, $source);
    gc_collect_cycles();
    expect($reference->get())->toBeNull()->and($profile->snapshot()['position_repeats'])->toBe(0)
        ->and($profile->snapshot()['strict_repeats'])->toBe(0)
        ->and($profile->snapshot()['active_traces'])->toBe(0);
    $grammar->parse('a', memoizationProfile: $profile);
    expect($profile->snapshot()['strict_repeats'])->toBe(0);
});

test('trivia identity is captured even when lexeme scope suppresses automatic spacing', function () {
    $grammar = (new Text('a'))->freeze(0);
    $root = (new ReflectionProperty(FrozenGrammar::class, 'root'))->getValue($grammar);
    $profile = new MemoizationProfile();
    $profile->registerFrozenGraph($root);
    $source = new ByteSource('a');
    $session = new ParseSession($source, trivia: new Text(' '), memoizationProfile: $profile);
    $root->recognizeInSession($source, 0, $session);
    expect($profile->snapshot()['limited_or_spaced_entries'])->toBe(1);
    $session->pushLexeme();
    $root->recognizeInSession($source, 0, $session);
    $session->pushSpacer(new Text(' '));
    $root->recognizeInSession($source, 0, $session);
    expect($profile->snapshot()['position_repeats'])->toBe(2)
        ->and($profile->snapshot()['policy_repeats'])->toBe(0);
});

test('strict ancestry identities do not retain temporary opaque callers', function () {
    $grammar = (new Text('a'))->freeze(0);
    $root = (new ReflectionProperty(FrozenGrammar::class, 'root'))->getValue($grammar);
    $profile = new MemoizationProfile();
    $profile->registerFrozenGraph($root);
    $source = new ByteSource('a');
    $session = new ParseSession($source, memoizationProfile: $profile);
    $make = static fn() => new class ($root) extends Parser {
        public function __construct(private Parser $operand)
        {
            // Only the child is registered; each temporary caller remains opaque.
        }

        public function __toString(): string
        {
            return 'caller';
        }

        protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
        {
            return $this->operand->recognizeInSession($source, $byteOffset, $session);
        }
    };
    for ($i = 0; $i < 2; ++$i) {
        $caller = $make();
        $reference = WeakReference::create($caller);
        $caller->recognizeInSession($source, 0, $session);
        unset($caller);
        expect($reference->get())->toBeNull();
    }
    expect($profile->snapshot()['policy_repeats'])->toBe(1)
        ->and($profile->snapshot()['strict_repeats'])->toBe(0);
});

test('context capture does not materialize deferred diagnostics', function () {
    $grammar = (new Text('a'))->freeze(0);
    $root = (new ReflectionProperty(FrozenGrammar::class, 'root'))->getValue($grammar);
    $profile = new MemoizationProfile();
    $profile->registerFrozenGraph($root);
    $source = new ByteSource('a');
    $session = new ParseSession($source, memoizationProfile: $profile);
    $pending = DeferredDiagnostic::merge(
        $source,
        new Diagnostic($source, 0, ['x'], [['caller']]),
        new Diagnostic($source, 0, ['y'], [['caller']]),
    );
    expect($pending)->toBeInstanceOf(DeferredDiagnostic::class);
    (new ReflectionProperty(ParseSession::class, 'deferredListFailure'))->setValue($session, $pending);
    $root->recognizeInSession($source, 0, $session);
    expect((new ReflectionProperty(DeferredDiagnostic::class, 'cache'))->getValue($pending))->toBeNull();
});

test('profiles bound graphs keys identities depth and serialized contexts', function () {
    $probe = new Not(new Text(str_repeat('a', 64) . '!'));
    $grammar = (new Sequence($probe, $probe, new Text(str_repeat('a', 64) . 'y')))->freeze(0);
    $small = new MemoizationProfile(capacity: 1, maxDepth: 1);
    $grammar->parse(str_repeat('a', 64) . 'y', memoizationProfile: $small);
    $counts = $small->snapshot();
    expect($counts['registered_nodes'])->toBeLessThanOrEqual(1)
        ->and($counts['tracked_positions'])->toBeLessThanOrEqual(1)
        ->and($counts['depth_dropped_entries'])->toBeGreaterThan(0)
        ->and($counts['graph_dropped_nodes'])->toBeGreaterThan(0)
        ->and($counts['active_traces'])->toBe(0);
    $bounded = new MemoizationProfile(capacity: 2, maxContextBytes: 1);
    $text = (new Text('a'))->freeze();
    for ($i = 0; $i < 8; ++$i) {
        $text->parse('a', memoizationProfile: $bounded);
    }
    expect($bounded->snapshot()['oversized_contexts'])->toBeGreaterThan(0)
        ->and($bounded->snapshot()['dropped_positions'])->toBeGreaterThan(0)
        ->and($bounded->snapshot()['policy_contexts'])->toBe(0)
        ->and($bounded->snapshot()['strict_contexts'])->toBe(0);
    $objects = array_fill(0, 8, null);
    foreach ($objects as &$object) {
        $object = new stdClass();
        $bounded->identity($object);
    }
    unset($object);
    expect($bounded->snapshot()['dropped_identities'])->toBeGreaterThan(0);
});

test('unknown executable unregistered and limited recognitions cannot supply reuse candidates', function () {
    $guarded = (new Text('a'))->guard(static fn(): bool => true)->freeze();
    $profile = new MemoizationProfile();
    $guarded->parse('a', memoizationProfile: $profile);
    $plain = new Text('a');
    $plain->parseAll(new ByteSource('a'), memoizationProfile: $profile);
    expect($profile->snapshot()['eligible_entries'])->toBe(0)
        ->and($profile->snapshot()['unregistered_or_impure_entries'])->toBe(2);
    $frozen = $plain->freeze();
    $frozen->parse('a', limits: new ParseLimits(maxWork: 10), memoizationProfile: $profile);
    expect($profile->snapshot()['limited_or_spaced_entries'])->toBe(1)
        ->and($profile->snapshot()['strict_repeats'])->toBe(0);
});

test('invalid profile bounds are rejected', function (int $capacity, int $depth, int $bytes) {
    expect(static fn() => new MemoizationProfile($capacity, $depth, $bytes))->toThrow(InvalidArgumentException::class);
})->with([[0, 1, 1], [1, 0, 1], [1, 1, 0]]);
