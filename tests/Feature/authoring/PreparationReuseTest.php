<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Builder\FreezeFlags;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;

test('shared prepared literals retain occurrence spans diagnostics and exhausted limits', function (): void {
    $shared = (new Sequence((new Text('ab'))->name('letters'), Repeat::exact(new Text('c'), 2)))
        ->name('item')->tag('shared');
    $parser = new Sequence(new Text('x'), $shared, new Text('|'), $shared);
    $plain = $parser->freeze(FreezeFlags::NONE);
    $prepared = $parser->freeze();
    foreach (['xabcc|abcc', 'xabcc|abc', 'xabcc|abdc', 'xabcc|abcc!'] as $input) {
        foreach ([new ByteSource('é' . $input), new Utf8Source('é' . $input)] as $source) {
            for ($warm = 0; $warm < 2; ++$warm) {
                $expected = $plain->parseAll($source, 2);
                $actual = $prepared->parseAll($source, 2);
                expect($actual->success)->toBe($expected->success)
                    ->and($actual->consumedByteLength)->toBe($expected->consumedByteLength)
                    ->and($actual->node)->toEqual($expected->node)
                    ->and($actual->ast())->toEqual($expected->ast())
                    ->and($actual->diagnostic)->toEqual($expected->diagnostic)
                    ->and($actual->value())->toBe($expected->value());
            }
            $budgets = array_merge(
                array_map(static fn(int $max): ParseLimits => new ParseLimits(maxWork: $max), range(1, 50)),
                array_map(static fn(int $max): ParseLimits => new ParseLimits(maxDepth: $max), range(1, 8)),
            );
            foreach ($budgets as $limits) {
                $observed = [];
                foreach ([$plain, $prepared] as $grammar) {
                    try {
                        $result = $grammar->parseAll($source, 2, $limits);
                        $observed[] = [$result->success, $result->node, $result->diagnostic];
                    } catch (ParseLimitException $exception) {
                        $observed[] = [
                            $exception->limit, $exception->byteOffset, $exception->rulePath, $exception->maximum,
                        ];
                    }
                }
                expect($observed[1])->toEqual($observed[0]);
            }
        }
    }
});
