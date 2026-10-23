<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Builder\FreezeFlags;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadWhileSet;
use Vanderlee\Comprehend\Parser\Structure\SeparatedList;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Tree\ConcreteNode;

test('list staging retains only accepted separator trivia and effects after a failed item', function (
    ?int $max,
    bool $trailing,
    int $flags,
): void {
    $events = [];
    $mapped = 0;
    $spacing = (new ReadWhileSet(' '))->onCommit(static function (ParseResult $result) use (&$events): void {
        $events[] = 'spacing:' . $result->start;
    });
    $separator = (new Text('::'))->pushResult('separators', '::')
        ->onCommit(static function (ParseResult $result) use (&$events): void {
            $events[] = 'separator:' . $result->start;
        });
    $item = (new Text('ab'))->mapValue(static function () use (&$mapped): object {
        ++$mapped;

        return (object) ['value' => 'AB'];
    })->onCommit(static function (ParseResult $result) use (&$events): void {
        $events[] = 'item:' . $result->start;
    });
    $grammar = (new SeparatedList($item, $separator, max: $max, allowTrailing: $trailing))
        ->spacing($spacing)->freeze($flags);
    $result = $grammar->matchPrefix('ab :: ax');

    expect($result->success)->toBeTrue()->and($result->rawText())->toBe($trailing ? 'ab ::' : 'ab')
        ->and($events)->toBe([])->and($mapped)->toBe(0);
    $value = $result->value();
    expect($value)->toHaveCount(1)->and($result->value()[0])->toBe($value[0])->and($mapped)->toBe(1)
        ->and($result->results())->toBe($trailing ? ['separators' => ['::']] : []);
    expect(array_map(static fn(ConcreteNode $node): string => $node->rawText(), $result->node->children))
        ->toBe($trailing ? ['ab', ' ', '::'] : ['ab']);
    $result->commit();
    $result->commit();
    expect($events)->toBe($trailing ? ['item:0', 'spacing:2', 'separator:3'] : ['item:0']);
})->with([null, 1])->with([false, true])->with([FreezeFlags::NONE, FreezeFlags::OPTIMIZE]);

test('bounded direct trailing separators append syntax only on complete success', function (
    string $input,
    string $accepted,
    array $children,
    int $flags,
): void {
    $grammar = (new SeparatedList(new Text('ab'), new Text('::'), max: 1, allowTrailing: true))
        ->spacing(new ReadWhileSet(' '))->freeze($flags);
    $result = $grammar->matchPrefix($input);
    expect($result->success)->toBeTrue()->and($result->rawText())->toBe($accepted)
        ->and($result->diagnostic)->toBeNull()->and($result->value())->toBe(['ab']);
    expect(array_map(static fn(ConcreteNode $node): string => $node->rawText(), $result->node->children))
        ->toBe($children);
})->with([
    ['ab :: ax', 'ab ::', ['ab', ' ', '::']],
    ['ab :x', 'ab', ['ab']],
    ['ab ', 'ab', ['ab']],
    ['ab::', 'ab::', ['ab', '', '::']],
])->with([FreezeFlags::NONE, FreezeFlags::OPTIMIZE]);
