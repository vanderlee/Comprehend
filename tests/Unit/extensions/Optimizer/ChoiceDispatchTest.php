<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Extensions\Optimizer\ChoiceDispatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Terminal\Set;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;

test('first-byte rejection retains ordinary work and diagnostic observations', function () {
    $parser = (new Text(str_repeat('a', 64)))->diagnosticRule('literal');
    $plan = ChoiceDispatch::prepare($parser);
    expect($plan)->not->toBeNull();
    foreach (['', 'b', "\u{e9}", "\0"] as $input) {
        foreach ([new ByteSource('>' . $input), new Utf8Source('>' . $input)] as $source) {
            $observations = [];
            foreach ([false, true] as $prepared) {
                $session = new ParseSession($source);
                $session->pushRule('caller');
                $session->recordFailure($source->byteLength(), 'inherited');
                $match = $prepared ? $plan->reject($source, 1, $session)
                    : $parser->recognizeInSession($source, 1, $session, retainFailureDiagnostic: false);
                $observations[] = [$match->length, $match->span, $match->farthestFailure,
                    $match->diagnostic, $session->diagnostic(true), $session->workUsed()];
                expect($session->rulePath())->toBe(['caller']);
            }
            expect($observations[1])->toEqual($observations[0]);
        }
    }
});

test('dispatch leaves candidates and unsafe scopes on their original path', function () {
    $source = new ByteSource('a');
    $plan = ChoiceDispatch::prepare(new Text('abc'));
    $session = new ParseSession($source);
    expect($plan->reject($source, 0, $session))->toBeNull()->and($session->workUsed())->toBe(0);
    foreach ([new ParseLimits(maxWork: 1), new ParseLimits(maxDepth: 1)] as $limits) {
        $session = new ParseSession(new ByteSource('b'), limits: $limits);
        expect($plan->reject($session->source(), 0, $session))->toBeNull()
            ->and($session->workUsed())->toBe(0);
    }
    $session = new ParseSession(new ByteSource('b'));
    $session->pushCaseSensitivity(false);
    expect($plan->reject($session->source(), 0, $session))->toBeNull();
});

test('dispatch excludes executable metadata non-ASCII literals and custom classes', function (Parser $parser) {
    expect(ChoiceDispatch::prepare($parser))->toBeNull();
})->with([
    'guard' => [(new Text('abc'))->guard(static fn(): bool => true)],
    'value' => [(new Text('abc'))->mapValue(static fn(): int => 1)],
    'effect' => [(new Text('abc'))->onCommit(static function (): void {
        // A registered effect prevents speculation even when its body does nothing.
    })],
    'label' => [(new Text('abc'))->label('keyword')],
    'insensitive' => [(new Text('abc'))->caseInsensitive()],
    'unicode' => [new Text("a\u{e9}")],
    'unknown' => [new Set('abc')],
    'subclass' => [new class ('abc') extends Text {
        // Inherited recognition does not establish the exact built-in capability.
    }],
]);
