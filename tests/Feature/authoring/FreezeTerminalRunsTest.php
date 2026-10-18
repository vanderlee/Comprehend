<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Directive\CaseSensitive;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Terminal\Any;
use Vanderlee\Comprehend\Parser\Terminal\Char;
use Vanderlee\Comprehend\Parser\Terminal\Range;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Runtime\ParseMetrics;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;

dataset('optimizable terminal runs', [
    'range' => [static fn() => new Range('a', 'z'), 'abcxyz', 'ABCXYZ', '!'],
    'open upper range' => [static fn() => new Range('a', null), 'abcxyz', 'ABCXYZ', '!'],
    'open lower range' => [static fn() => new Range(null, 'z'), 'abcxyz', 'ABCXYZ', '~'],
    'character' => [static fn() => new Char('a'), 'aaaaaa', 'AAAAAA', '!'],
    'excluded character' => [static fn() => new Char('a', false), 'bbbbbb', 'BBBBBB', 'a'],
    'single character text' => [static fn() => new Text('a'), 'aaaaaa', 'AAAAAA', '!'],
    'any unit' => [static fn() => new Any(), "ab\0c\nd", "AB\0C\nD", ''],
]);

test('terminal run rewrites reduce visits while retaining all result structure', function (
    callable $factory,
    string $text,
    string $upper,
    string $stop,
) {
    foreach ([true, false] as $sensitive) {
        $input = '#' . ($sensitive ? $text : $upper) . $stop;
        foreach ([new ByteSource($input), new Utf8Source($input)] as $source) {
            $repeat = (new Repeat($factory()))->name('run', 'runs')->tag('outer');
            $parser = new CaseSensitive($sensitive, $repeat);
            $optimized = (new GraphSnapshot(optimize: true))->copy($parser);
            $counts = [];
            $nodes = [];
            foreach ([$parser, $optimized] as $graph) {
                $metrics = new ParseMetrics();
                $result = new ParseResult($graph->recognizeInSession(
                    $source,
                    1,
                    new ParseSession($source, metrics: $metrics),
                ));
                expect($result->rawText())->toBe($sensitive ? $text : $upper);
                $nodes[] = $result->node;
                $counts[] = $metrics->snapshot()['parser_entries'];
            }
            expect($nodes[1])->toEqual($nodes[0])->and($counts[1])->toBeLessThan($counts[0]);
            $direct = $parser->parseAll($source, 1);
            $frozen = $parser->freeze()->parseAll($source, 1);
            expect($frozen->success)->toBe($direct->success)->and($frozen->diagnostic)->toEqual($direct->diagnostic);
        }
    }
})->with('optimizable terminal runs');

test('terminal runs preserve bounds failure diagnostics and local case overrides', function (
    callable $factory,
    string $text,
    string $upper,
    string $stop,
) {
    foreach ([[0, 0], [0, 2], [2, 2], [7, null], [1, null]] as [$min, $max]) {
        foreach (['', $text . $stop, $upper . $stop] as $input) {
            $source = new Utf8Source($input);
            $child = $factory();
            if (!$child instanceof Any) {
                $child->caseInsensitive();
            }
            $parser = new CaseSensitive(true, new Repeat($child, $min, $max));
            $direct = $parser->parseAll($source);
            $frozen = $parser->freeze()->parseAll($source);
            expect($frozen->success)->toBe($direct->success)->and($frozen->node)->toEqual($direct->node)
                ->and($frozen->diagnostic)->toEqual($direct->diagnostic)
                ->and($frozen->farthestFailure)->toBe($direct->farthestFailure);
        }
    }
})->with('optimizable terminal runs');

test('new terminal runs preserve metadata effects trivia and budget failures', function (
    callable $factory,
    string $text,
) {
    $effects = 0;
    $child = $factory()->name('unit')->tag('child')->onCommit(static function () use (&$effects) {
        ++$effects;
    });
    $repeat = Repeat::star($child)->name('run')->mapValue(static fn(ParseResult $r) => $r->rawText());
    $source = new ByteSource($text);
    $result = $repeat->freeze()->parse($source);
    expect($result->node)->toEqual($repeat->parseAll($source)->node)->and($effects)->toBe(0);
    $result->commit();
    $result->commit();
    expect($effects)->toBe(strlen($text));

    $repeat = Repeat::star($factory())->spacing(Repeat::star(new Text(' ')));
    $source = new ByteSource(implode(' ', str_split($text)));
    expect($repeat->freeze()->parse($source)->node)->toEqual($repeat->parseAll($source)->node);

    foreach ([new ParseLimits(maxWork: 3), new ParseLimits(maxDepth: 1)] as $limits) {
        $errors = [];
        $repeat = Repeat::star($factory());
        foreach ([$repeat, $repeat->freeze()] as $parser) {
            try {
                $parser->parseAll(new ByteSource($text), limits: $limits);
            } catch (ParseLimitException $error) {
                $errors[] = $error->getMessage();
            }
        }
        expect($errors)->toHaveCount(2)->and($errors[1])->toBe($errors[0]);
    }
})->with('optimizable terminal runs');

test('unsafe terminal rewrites retain recognition and validation', function (Parser $child, string $text) {
    $repeat = Repeat::star($child);
    foreach ([new ByteSource($text), new Utf8Source($text)] as $source) {
        $observed = [];
        foreach ([$repeat, (new GraphSnapshot(optimize: true))->copy($repeat)] as $parser) {
            try {
                $result = $parser->parseAll($source);
                $observed[] = [$result->success, $result->node, $result->diagnostic];
            } catch (InvalidArgumentException $error) {
                $observed[] = [$error::class, $error->getMessage()];
            }
        }
        expect($observed[1])->toEqual($observed[0]);
    }
})->with([
    'reversed' => [new Range('z', 'a'), 'az'],
    'reversed after folding' => [(new Range('Z', '['))->caseInsensitive(), 'Z['],
    'excluded range' => [new Range('a', 'z', false), '!abc'],
    'unicode range' => [new Range("\u{e0}", "\u{ff}"), "\u{e9}"],
    'unicode character' => [new Char("\u{e9}"), "\u{e9}"],
    'unicode text' => [new Text("\u{e9}"), "\u{e9}"],
    'multiple text units' => [new Text('ab'), 'ababab'],
    'custom range' => [new class ('a', 'z') extends Range {
        public function __toString(): string
        {
            return 'custom range';
        }
    }, 'abc'],
    'custom character' => [new class ('a') extends Char {
        public function __toString(): string
        {
            return 'custom character';
        }
    }, 'aaa'],
    'custom text' => [new class ('a') extends Text {
        public function __toString(): string
        {
            return 'custom text';
        }
    }, 'aaa'],
    'custom any' => [new class extends Any {
        public function __toString(): string
        {
            return 'custom any';
        }
    }, 'abc'],
]);

test('any runs ignore case folding and keep each multibyte unit', function () {
    $source = new Utf8Source("\u{130}\u{e9}\0\n");
    $parser = new CaseSensitive(false, Repeat::star(new Any()));
    $direct = $parser->parseAll($source);
    $frozen = $parser->freeze()->parse($source);
    expect($frozen->success)->toBeTrue()->and($frozen->node)->toEqual($direct->node);
});
