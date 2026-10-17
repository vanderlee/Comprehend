<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Set;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Parser\Terminal\ReadWhileSet;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;

test('frozen scans preserve byte spans trees values and diagnostics', function (string $text, int $min, ?int $max) {
    foreach ([new ByteSource($text), new Utf8Source($text)] as $source) {
        $parser = new Repeat((new Set('aé'))->caseInsensitive(), $min, $max);
        $parser->name('word', 'words')->tag('letters')->diagnosticRule('word-rule');
        $direct = $parser->parseAll($source);
        $frozen = $parser->freeze()->parseAll($source);
        expect($frozen->success)->toBe($direct->success)
            ->and($frozen->consumedByteLength)->toBe($direct->consumedByteLength)
            ->and($frozen->node)->toEqual($direct->node)
            ->and($frozen->ast())->toEqual($direct->ast())
            ->and($frozen->diagnostic)->toEqual($direct->diagnostic)
            ->and($frozen->value())->toBe($direct->value());
    }
})->with([
    ['', 0, null], ['Aéa', 0, null], ['Aé!', 0, null], ['Aé', 3, null],
    ['Aéa', 1, 2], ['Aé', 2, 2], ['', 0, 0], ['!', 1, null],
]);

test('repeat metadata remains lazy and committed effects run once', function () {
    $effects = 0;
    $maps = 0;
    $parser = Repeat::star(new Set('ab'));
    $parser->name(static fn($node) => count($node->children) === 3 ? 'three' : 'other', 'group', true)
        ->tag('outer')->guard(static fn($node) => count($node->children) === 3)
        ->mapValue(static function (ParseResult $result) use (&$maps) {
            ++$maps;
            return strtoupper($result->rawText());
        })->validateSemantic(static fn(ParseResult $result) => $result->value() === 'ABA')
        ->onCommit(static function () use (&$effects) {
            ++$effects;
        });
    $result = $parser->freeze()->parse('aba');
    expect($result->success)->toBeTrue()->and($maps)->toBe(0)->and($effects)->toBe(0)
        ->and($result->node?->name)->toBe('three')->and($result->node?->tags)->toBe(['outer'])
        ->and($result->value())->toBe('ABA')->and($result->value())->toBe('ABA')->and($maps)->toBe(1);
    $result->commit();
    $result->commit();
    expect($effects)->toBe(1);
});

test('child metadata and lifecycle are retained for every occurrence', function () {
    $effects = 0;
    $child = (new Set('ab'))->name('letter')->tag('inner')
        ->mapValue(static fn(ParseResult $r) => strtoupper($r->rawText()))
        ->guard(static fn($node) => $node->rawText() !== 'b')
        ->onCommit(static function () use (&$effects) {
            ++$effects;
        });
    $parser = Repeat::star($child);
    $source = new ByteSource('aab');
    $direct = $parser->matchPrefix($source);
    $frozen = $parser->freeze()->matchPrefix($source);
    expect($frozen->node)->toEqual($direct->node)->and($frozen->rawText())->toBe('aa');
    $frozen->commit();
    expect($effects)->toBe(2);
});

test('inherited spacing and explicit spacing overrides retain their syntax', function () {
    foreach ([Repeat::star(new Set('ab')), Repeat::star(new Set('ab'))->spacing(null)] as $repeat) {
        $parser = (new Sequence(new Text('['), $repeat, new Text(']')))->spacing(Repeat::star(new Text(' ')));
        foreach (['[ a b ]', '[ ab ]'] as $text) {
            $source = new ByteSource($text);
            $direct = $parser->parseAll($source);
            $frozen = $parser->freeze()->parse($source);
            expect($frozen->success)->toBe($direct->success)->and($frozen->node)->toEqual($direct->node)
                ->and($frozen->diagnostic)->toEqual($direct->diagnostic);
        }
    }
});

test('freeze retains custom set recognition and excluded sets', function () {
    $custom = new class ('ab') extends Set {
        public function repetitionScanner(int $min, ?int $max): ?ReadWhileSet
        {
            throw new LogicException('Custom optimization hooks must not run');
        }
    };
    foreach ([$custom, new Set('ab', false)] as $child) {
        $parser = Repeat::star($child);
        $source = new ByteSource('ab!');
        expect($parser->freeze()->matchPrefix($source)->node)->toEqual($parser->matchPrefix($source)->node);
    }
});

test('frozen repeat keeps work and depth limit failures', function () {
    $parser = Repeat::star(new Set('ab'));
    $source = new ByteSource('abba');
    foreach ([new ParseLimits(maxWork: 5), new ParseLimits(maxDepth: 1)] as $limits) {
        $failures = [];
        foreach ([$parser, $parser->freeze()] as $grammar) {
            try {
                $grammar->parseAll($source, limits: $limits);
            } catch (ParseLimitException $error) {
                $failures[] = $error->getMessage();
            }
        }
        expect($failures)->toHaveCount(2)->and($failures[1])->toBe($failures[0]);
    }
});

test('zero maximum never inspects the set and frozen snapshots stay detached', function () {
    $empty = new Repeat(new Set("\xff"), 0, 0);
    expect($empty->freeze()->parse('')->success)->toBeTrue();
    $child = new Set('a');
    $repeat = Repeat::star($child)->name('before')->tag('before');
    $frozen = $repeat->freeze();
    $child->guard(static fn() => false);
    $repeat->name('after')->tag('after');
    $result = $frozen->parse('aaa');
    expect($result->success)->toBeTrue()->and($result->node?->name)->toBe('before')
        ->and($result->node?->tags)->toBe(['before']);
});
