<?php

declare(strict_types=1);

namespace Tests\Extensions\Optimizer;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Not;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Set;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ParseMetrics;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;

uses(TestCase::class);

test('freeze rewrites shared repeated sets without changing authoring graphs or concrete trees', function () {
    $repeat = Repeat::star(new Set('ab'));
    $graph = new Sequence($repeat, $repeat);
    $optimized = (new GraphSnapshot(optimize: true))->copy($graph);
    $this->assertSame($optimized->children()[0], $optimized->children()[1]);
    $source = new ByteSource(str_repeat('ab', 30));
    $counts = [];
    foreach ([$graph, (new GraphSnapshot())->copy($graph), $optimized] as $parser) {
        $metrics = new ParseMetrics();
        $match = $parser->recognizeInSession($source, 0, new ParseSession($source, metrics: $metrics));
        $this->assertEquals($graph->parseAll($source)->node, $match->node);
        $counts[] = $metrics->snapshot()['parser_entries'];
    }
    $this->assertSame($counts[0], $counts[1]);
    $this->assertLessThan($counts[0], $counts[2]);
});

test('replacement configuration stays lazy and snapshots remain reusable and isolated', function (
    Parser $parser,
    string $input,
): void {
    $mapped = 0;
    $committed = 0;
    $parser->name('result', 'numbers')->tag('selected')
        ->guard(static fn(): bool => true)
        ->mapValue(static function (ParseResult $result) use (&$mapped): string {
            ++$mapped;
            return $result->rawText();
        })
        ->onCommit(static function () use (&$committed): void {
            ++$committed;
        });
    $snapshot = new GraphSnapshot(optimize: true);
    $copy = $snapshot->copy($parser);
    $plain = (new GraphSnapshot())->copy($copy);
    $this->assertTrue($snapshot->isReusable());
    $this->assertNotSame($parser, $copy);
    $this->assertNotSame($copy, $plain);
    foreach ([$parser, $copy, $plain] as $candidate) {
        $result = $candidate->parseAll(new ByteSource($input));
        $this->assertEquals($parser->parseAll(new ByteSource($input))->node, $result->node);
        $before = $mapped;
        $this->assertSame($input, $result->value());
        $this->assertSame($input, $result->value());
        $this->assertSame($before + 1, $mapped);
        $before = $committed;
        $result->commit();
        $result->commit();
        $this->assertSame($before + 1, $committed);
    }
})->with([
    'repeat' => [Repeat::star(new Set('ab')), 'aba'],
    'choice' => [new Choice(new Text('a'), new Text('b')), 'a'],
    'not' => [new Not(new Text('a')), ''],
]);
