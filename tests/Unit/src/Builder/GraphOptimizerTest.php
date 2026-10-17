<?php

declare(strict_types=1);

namespace Tests\Src\Builder;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Set;
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
