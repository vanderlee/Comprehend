<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Builder\FrozenGrammar;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Not;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Regex;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ChoicePolicy;
use Vanderlee\Comprehend\Runtime\MemoizationProfile;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\Utf8FileSource;
use Vanderlee\Comprehend\Source\Utf8Source;

test('profiling retains ordered diagnostics trees values and work under both freeze modes', function () {
    $prefix = new Text(str_repeat('a', 64));
    foreach ([0, 1] as $flags) {
        foreach (ChoicePolicy::cases() as $policy) {
            $parser = (new Choice(new Sequence($prefix, new Text('x')), new Sequence($prefix, new Text('y'))))
                ->setPreference($policy);
            $grammar = $parser->freeze($flags);
            $root = (new ReflectionProperty(FrozenGrammar::class, 'root'))->getValue($grammar);
            foreach ([str_repeat('a', 64) . 'y', str_repeat('a', 64) . '!', 'a', "a\u{e9}"] as $input) {
                foreach ([new ByteSource('>' . $input), new Utf8Source('>' . $input)] as $source) {
                    $profile = new MemoizationProfile();
                    for ($pass = 0; $pass < 2; ++$pass) {
                        $expected = $grammar->parseAll($source, 1);
                        $actual = $grammar->parseAll($source, 1, memoizationProfile: $profile);
                        expect($actual->success)->toBe($expected->success)
                            ->and($actual->consumedByteLength)->toBe($expected->consumedByteLength)
                            ->and($actual->node)->toEqual($expected->node)
                            ->and($actual->diagnostic)->toEqual($expected->diagnostic)
                            ->and($actual->ast())->toEqual($expected->ast())
                            ->and($actual->value())->toBe($expected->value());
                    }
                    $work = [];
                    foreach ([null, $profile] as $observer) {
                        $session = new ParseSession($source, memoizationProfile: $observer);
                        $root->recognizeInSession($source, 1, $session);
                        $work[] = $session->workUsed();
                    }
                    expect($work[1])->toBe($work[0])->and($profile->snapshot()['active_traces'])->toBe(0);
                }
            }
        }
    }
});

test('profiling never evaluates semantics or effects or suppresses guards and exceptions', function () {
    $events = [];
    $operand = (new Regex('/a+/A'))->guard(static function () use (&$events): bool {
        $events[] = 'guard';
        return true;
    })->setResult('selected', static function () use (&$events): int {
        $events[] = 'collection';
        return 3;
    })->onCommit(static function () use (&$events): void {
        $events[] = 'commit';
    });
    $grammar = (new Sequence(new Not(new Text('!')), $operand))->mapValue(
        static function (ParseResult $result) use (&$events): int {
            $events[] = 'value';
            return $result->result('selected');
        },
    )->freeze();
    $profile = new MemoizationProfile();
    $result = $grammar->parse('aaa', memoizationProfile: $profile);
    expect($events)->toBe(['guard'])->and($profile->snapshot()['unregistered_or_impure_entries'])->toBeGreaterThan(0);
    expect($result->value())->toBe(3)->and($result->value())->toBe(3);
    $result->commit();
    $result->commit();
    expect($events)->toBe(['guard', 'value', 'collection', 'commit']);
    $throwing = (new Text('a'))->guard(static function (): bool {
        throw new RuntimeException('guard failed');
    })->freeze();
    expect(static fn() => $throwing->parse('a', memoizationProfile: $profile))
        ->toThrow(RuntimeException::class, 'guard failed');
    expect($profile->snapshot()['active_traces'])->toBe(0)
        ->and($profile->snapshot()['aborted_entries'])->toBeGreaterThan(0);
});

test('file trivia and exhausted limits keep their original outcomes with profiling enabled', function () {
    $stream = tmpfile();
    if ($stream === false) {
        throw new RuntimeException('Could not create profiling fixture');
    }
    fwrite($stream, '>a a!');
    $source = new Utf8FileSource($stream, 4);
    fclose($stream);
    try {
        $grammar = Repeat::plus(new Text('a'))->spacing(Repeat::star(new Text(' ')))->freeze();
        $profile = new MemoizationProfile();
        $limits = [null, new ParseLimits(maxDepth: 1), new ParseLimits(maxDepth: 3)];
        for ($work = 1; $work <= 30; ++$work) {
            $limits[] = new ParseLimits(maxWork: $work);
        }
        foreach ($limits as $limit) {
            $observations = [];
            foreach ([null, $profile] as $observer) {
                try {
                    $result = $grammar->parseAll($source, 1, $limit, $observer);
                    $observations[] = [$result->success, $result->node, $result->diagnostic];
                } catch (ParseLimitException $error) {
                    $observations[] = [$error->getMessage(), $error->byteOffset];
                }
            }
            expect($observations[1])->toEqual($observations[0])
                ->and($profile->snapshot()['active_traces'])->toBe(0);
        }
        expect($profile->snapshot()['limited_or_spaced_entries'])->toBeGreaterThan(0);
    } finally {
        $source->close();
    }
});

test('opaque callbacks never become a memoization capability', function () {
    $parser = new class extends Parser {
        public function __toString(): string
        {
            return 'opaque';
        }

        protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
        {
            return $this->success($source, $byteOffset);
        }
    };
    $profile = new MemoizationProfile();
    expect($parser->freeze()->parse('', memoizationProfile: $profile)->success)->toBeTrue()
        ->and($profile->snapshot()['eligible_entries'])->toBe(0);
});
