<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Extensions\Optimizer\ChoicePrefix;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;

test('prefix plans reject only later mismatches with original progress work and diagnostics', function () {
    $prefix = str_repeat('a', 40);
    $choice = new Choice(...array_map(
        static fn(string $suffix): Text => new Text($prefix . $suffix),
        ['x', 'y', 'z', 'zz'],
    ));
    $plan = ChoicePrefix::prepare($choice);
    expect($plan)->not->toBeNull();
    $source = new ByteSource('>' . $prefix . '!');
    $examined = [];
    foreach ($choice as $index => $parser) {
        $session = new ParseSession($source);
        $session->pushRule('caller');
        $failure = $plan->reject($index, $source, 1, $session, $examined);
        if ($index < 2) {
            expect($failure)->toBeNull()->and($session->workUsed())->toBe(0);
            continue;
        }
        $ordinary = new ParseSession($source);
        $ordinary->pushRule('caller');
        $expected = $parser->recognizeInSession($source, 1, $ordinary, retainFailureDiagnostic: false);
        expect($failure->length)->toBe($expected->length)
            ->and($failure->span)->toEqual($expected->span)
            ->and($session->workUsed())->toBe($ordinary->workUsed())
            ->and($session->diagnostic())->toEqual($ordinary->diagnostic());
    }
});

test('prefix preparation rejects short diverse executable custom and oversized graphs', function () {
    $prefix = str_repeat('a', 40);
    $plain = static fn(): array => array_map(
        static fn(string $suffix): Text => new Text($prefix . $suffix),
        ['x', 'y', 'z', 'w'],
    );
    $guarded = $plain();
    $guarded[2]->guard(static fn(): bool => true);
    $custom = $plain();
    $custom[2] = new class ($prefix . 'z') extends Text {
        // Inherited Text behavior does not establish a custom parser's purity.
    };
    $insensitive = $plain();
    $insensitive[2]->caseInsensitive();
    foreach (
        [new Choice(new Text('a'), new Text('b'), new Text('c'), new Text('d')),
            new Choice(...array_slice($plain(), 0, 3)), new Choice(...$guarded), new Choice(...$custom),
            new Choice(...$insensitive), new Choice(...array_fill(0, 257, new Text($prefix))),
            new Choice(...array_fill(0, 4, new Text(str_repeat('a', 65537)))),
            new Choice(...array_fill(0, 5, new Text(str_repeat('a', 65536)))),
            new Choice(...array_map(static fn(string $s): Text => new Text($s . $prefix), ['a', 'b', 'c', 'd'])),
            new Choice(...array_fill(0, 4, new Text($prefix . "\u{e9}")))] as $choice
    ) {
        expect(ChoicePrefix::prepare($choice))->toBeNull();
    }
    $deep = [];
    for ($i = 0; $i < 40; ++$i) {
        $deep[] = new Text($prefix . str_repeat('b', $i));
    }
    expect(ChoicePrefix::prepare(new Choice(...$deep)))->toBeNull();
});

test('prefix eligibility includes bounded endpoints and excludes the preceding shared-prefix length', function () {
    foreach ([31, 32] as $bytes) {
        $choice = new Choice(...array_map(
            static fn(string $suffix): Text => new Text(str_repeat('a', $bytes) . $suffix),
            ['xx', 'yy', 'zz', 'ww'],
        ));
        expect(ChoicePrefix::prepare($choice) !== null)->toBe($bytes === 32);
    }
    expect(ChoicePrefix::prepare(new Choice(...array_fill(0, 4, new Text(str_repeat('a', 65536))))))
        ->not->toBeNull();
    expect(ChoicePrefix::prepare(new Choice(...array_fill(0, 256, new Text(str_repeat('a', 40))))))
        ->not->toBeNull();
});
