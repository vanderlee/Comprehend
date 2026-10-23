<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ChoicePolicy;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteFileSource;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8FileSource;
use Vanderlee\Comprehend\Source\Utf8Source;
use Vanderlee\Comprehend\Tools\ChoicePlans\ChoicePlanProbe;

require_once dirname(__DIR__, 3) . '/tools/choice-plans/ChoicePlanProbe.php';

test('lazy prefix plans preserve overlapping literals empty input ties and failure order', function () {
    $inputs = ['', 'a', 'b', 'aa', 'ab', 'ba', 'bb', 'aaa', 'aab', 'aba', 'abb', 'bab', 'abc', "a\u{e9}"];
    $grammars = [['ab', 'aa', 'a'], ['b', 'ab', 'ab', 'aba'], ['ab', 'a', 'abc'], ['aaa', 'aab', 'abb', 'ba']];
    foreach ($grammars as $literals) {
        $probe = new ChoicePlanProbe($literals, factored: true, lazy: true);
        $plain = new Choice(...array_map(static fn(string $literal): Text => new Text($literal), $literals));
        foreach (ChoicePolicy::cases() as $policy) {
            $probe->setPreference($policy);
            $plain->setPreference($policy);
            foreach ($inputs as $input) {
                foreach ([new ByteSource('>' . $input), new Utf8Source('>' . $input)] as $source) {
                    foreach (['matchPrefix', 'parseAll'] as $operation) {
                        $expected = ChoicePlanProbe::fingerprint($plain->$operation($source, 1));
                        expect(ChoicePlanProbe::fingerprint($probe->$operation($source, 1)))->toBe($expected);
                    }
                }
            }
        }
    }
});

test('lazy prefix plans preserve scoped policies inherited diagnostics work and exact exhausted budgets', function () {
    $literals = ['ABx', 'ABy', 'AB', 'z'];
    $probe = new ChoicePlanProbe($literals, factored: true, lazy: true);
    $plain = new Choice(...array_map(static fn(string $literal): Text => new Text($literal), $literals));
    $limits = [null, new ParseLimits(maxDepth: 1), new ParseLimits(maxDepth: 2)];
    for ($work = 1; $work <= 25; ++$work) {
        $limits[] = new ParseLimits(maxWork: $work);
    }
    foreach (ChoicePolicy::cases() as $policy) {
        foreach ([true, false] as $caseSensitive) {
            foreach ([null, new Text(' ')] as $spacing) {
                foreach ($limits as $limit) {
                    $source = new Utf8Source('>ABy');
                    $observations = [];
                    foreach ([$plain, $probe] as $parser) {
                        $session = new ParseSession(
                            $source,
                            trivia: $spacing,
                            caseSensitive: $caseSensitive,
                            preference: $policy,
                            limits: $limit,
                        );
                        $session->pushRule('caller');
                        $session->recordFailure(1, 'inherited', [['earlier']]);
                        try {
                            $result = new ParseResult($parser->recognizeInSession($source, 1, $session));
                            $observations[] = [ChoicePlanProbe::fingerprint($result), $session->workUsed()];
                        } catch (ParseLimitException $error) {
                            $observations[] = [$error->getMessage(), $error->byteOffset, $session->workUsed()];
                        }
                        expect($session->rulePath())->toBe(['caller'])
                            ->and($session->getPreference())->toBe($policy)
                            ->and($session->isCaseSensitive())->toBe($caseSensitive);
                    }
                    expect($observations[1])->toBe($observations[0]);
                }
            }
        }
    }
});

test('lazy first success avoids reading later literal windows', function () {
    $stream = tmpfile();
    fwrite($stream, 'a' . str_repeat('b', 8192));
    $source = new class ($stream, 4) extends ByteFileSource {
        public int $largestSlice = 0;
        public bool $rejectLargeSlice = false;

        public function sliceBytes(int $start, int $end): string
        {
            if ($this->rejectLargeSlice && $end - $start > 1) {
                throw new RuntimeException('window unavailable');
            }
            $this->largestSlice = max($this->largestSlice, $end - $start);

            return parent::sliceBytes($start, $end);
        }
    };
    fclose($stream);
    try {
        $probe = new ChoicePlanProbe(['a', str_repeat('b', 8192)], factored: true, lazy: true);
        $result = $probe->matchPrefix($source);
        expect($result->success)->toBeTrue()->and($result->consumedByteLength)->toBe(1)
            ->and($source->largestSlice)->toBe(1);
        $probe->preferLongest();
        $source->rejectLargeSlice = true;
        $session = new ParseSession($source);
        expect(fn() => $probe->recognizeInSession($source, 0, $session))
            ->toThrow(RuntimeException::class, 'window unavailable');
        expect($session->rulePath())->toBe([])->and($session->getPreference())->toBe(ChoicePolicy::FIRST);
        $source->rejectLargeSlice = false;
        expect($probe->matchPrefix($source)->consumedByteLength)->toBe(1);
    } finally {
        $source->close();
    }
});

test('lazy prefix plans restore per-parse state after exceptions and retain fresh semantic ownership', function () {
    $stream = tmpfile();
    fwrite($stream, '>abz');
    $source = new Utf8FileSource($stream, 4);
    $sourceReference = WeakReference::create($source);
    fclose($stream);
    $probe = new ChoicePlanProbe(['abx', 'aby', 'abz'], factored: true, lazy: true);
    $events = [];
    $probe->mapValue(function (ParseResult $result) use (&$events): object {
        $events[] = 'value';

        return (object) ['text' => $result->rawText()];
    })->onCommit(function () use (&$events): void {
        $events[] = 'commit';
    });
    $throw = true;
    $probe->guard(static function () use (&$throw): bool {
        if ($throw) {
            $throw = false;
            throw new RuntimeException('probe guard');
        }

        return true;
    });
    try {
        expect(fn() => $probe->parseAll($source, 1))->toThrow(RuntimeException::class, 'probe guard');
        $first = $probe->parseAll($source, 1);
        $second = $probe->parseAll(new Utf8Source('>abz'), 1);
        expect($events)->toBe([])->and($first->node)->not->toBe($second->node);
        $value = $first->value();
        expect($first->value())->toBe($value)->and($second->value())->not->toBe($value);
        $first->commit();
        $first->commit();
        expect($events)->toBe(['value', 'value', 'commit'])->and($second->isCommitted())->toBeFalse();
    } finally {
        $source->close();
    }
    unset($first, $second, $source, $value);
    gc_collect_cycles();
    expect($sourceReference->get())->toBeNull();
    $result = $probe->parseAll(new ByteSource('aby'));
    expect($result->success)->toBeTrue()->and($result->rawText())->toBe('aby');
});
