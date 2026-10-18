<?php

declare(strict_types=1);

namespace Tests\Src\Builder;

use LogicException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\RuleReference;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;

uses(TestCase::class);

test('extraction a target detaches the reference from redefinitions', function (): void {
    $rules = new Ruleset(['item' => new Text('a')]);
    $reference = new RuleReference($rules, 'item');
    $reference->bindSnapshotTarget($reference->graphTarget());
    $rules->define('item', new Text('b'));

    $this->assertSame('item', (string) $reference);
    $this->assertTrue($reference->parseAll(new ByteSource('a'))->success);
    $this->assertFalse($reference->parseAll(new ByteSource('b'))->success);
});

test('unresolved reference reports its rule name', function (): void {
    $reference = new RuleReference(new Ruleset(), 'missing');
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Undefined grammar rule: missing');

    $reference->graphTarget();
});

test('freezing preserves independent guards on references to the same production', function (): void {
    $rules = new Ruleset(['item' => new Text('a')]);
    $parser = new Choice($rules->ref('item')->guard(static fn(): bool => false), $rules->ref('item'));

    expect($parser->parseAll(new ByteSource('a'))->success)->toBeTrue();
    expect($parser->freeze()->parse('a')->success)->toBeTrue();
});

test('freezing preserves reference names mappings validators and commit effects', function (): void {
    $rules = new Ruleset(['item' => new Text('a')]);
    $effects = [];
    $reference = static function (string $name) use ($rules, &$effects): RuleReference {
        return $rules->ref('item')->name($name)->tag($name)
            ->mapValue(static fn(): string => $name)
            ->validateSemantic(static fn($result): bool => $result->value() === $name)
            ->onCommit(static function ($result) use (&$effects): void {
                $effects[] = $result->value();
            });
    };
    $parser = new Sequence($reference('first'), $reference('second'));
    $result = $parser->freeze()->parse('aa');

    expect($result->success)->toBeTrue();
    expect(array_map(static fn($node) => $node->name, $result->ast()->children))->toBe(['first', 'second']);
    expect($result->ast()->children[1]->tags)->toBe(['second']);
    expect($result->validate()->valid)->toBeTrue();
    expect($effects)->toBe([]);
    expect($result->commit())->toBeTrue();
    expect($result->commit())->toBeTrue();
    expect($effects)->toBe(['first', 'second']);
});

test('freezing resolves a shared recursive production once per argument set', function (): void {
    $builds = 0;
    $rules = new Ruleset();
    $rules->define('item', static function (string $text) use ($rules, &$builds): Parser {
        ++$builds;

        return new Choice(new Sequence(new Text($text), $rules->ref('item', $text)), new Text($text));
    });
    $parser = new Sequence($rules->ref('item', 'a')->name('first'), $rules->ref('item', 'b')->name('second'));
    $frozen = $parser->freeze();

    expect($builds)->toBe(2);
    expect($frozen->parse('aaabbb')->success)->toBeTrue();
    expect($builds)->toBe(2);
});
