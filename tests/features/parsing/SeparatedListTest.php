<?php

declare(strict_types=1);

namespace Tests\features\parsing;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Parser\Structure\SeparatedList;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Source\ByteSource;

uses(TestCase::class);

test('facade static fluent syntax and ruleset lists share values and byte spans', function (): void {
    $item = C::text('é')->named('item')->mapText(static fn(string $text): int => strlen($text));
    $forms = [
        $item->separatedBy(',', min: 1, allowTrailing: true),
        C::separatedBy($item, ',', min: 1, allowTrailing: true),
        C::syntax()->separatedBy($item, ',', min: 1, allowTrailing: true),
    ];
    $rules = new Ruleset();
    $rules->define('list', $forms[0])->root('list');
    $forms[] = $rules->freeze();
    foreach ($forms as $form) {
        $result = $form->parseAll('é,é,');
        $this->assertTrue($result->success);
        $this->assertSame([2, 2], $result->value());
        $this->assertSame(6, $result->consumedByteLength);
        $this->assertSame('é,é,', $result->rawText());
    }
    $result = $forms[0]->parse('é,é,');
    $this->assertSame(
        [0, 3],
        array_map(static fn($node): int => $node->span->start, $result->ast()->allNamed('item')),
    );
    $this->assertSame(
        ['é', ',', 'é', ','],
        array_map(static fn($node): string => $node->rawText(), $result->node->children),
    );
});

test('list values are lazy cached and independent of accepted separator values', function (): void {
    $calls = 0;
    $item = C::text('a')->mapText(static function () use (&$calls): int {
        return ++$calls;
    });
    $separator = C::text(',')->mapText(static function (): never {
        throw new RuntimeException('Separator value must stay lazy');
    });
    $result = $item->separatedBy($separator, allowTrailing: true)->parse('a,a,');
    $this->assertSame(0, $calls);
    $result->ast();
    $this->assertSame([1, 2], $result->value());
    $this->assertSame([1, 2], $result->value());
    $this->assertSame(2, $calls);
    $this->assertSame(4, $item->separatedBy($separator, allowTrailing: true)
        ->mapValue(static fn(ParseResult $result): int => strlen($result->rawText()))->parse('a,a,')->value());
    $this->assertSame(2, $calls);
});

test('list commits selected item separator and trivia effects once in source order', function (): void {
    $effects = [];
    $effect = static function (ParseResult $result) use (&$effects): void {
        $effects[] = $result->rawText();
    };
    $item = C::text('a')->pushResult('items')->onCommit($effect);
    $separator = C::text(',')->pushResult('separators')->onCommit($effect);
    $list = $item->separatedBy($separator, allowTrailing: true)->spacing(C::text(' ')->onCommit($effect));
    $result = $list->matchPrefix('a , a , x');
    $this->assertSame('a , a ,', $result->rawText());
    $this->assertSame(['a', 'a'], $result->value());
    $this->assertSame(['items' => ['a', 'a'], 'separators' => [',', ',']], $result->results());
    $this->assertSame([], $effects);
    $this->assertTrue($result->commit());
    $this->assertTrue($result->commit());
    $this->assertSame(['a', ' ', ',', ' ', 'a', ' ', ','], $effects);
    $this->assertSame(
        'a , a ,',
        implode('', array_map(static fn($node): string => $node->rawText(), $result->node->children)),
    );
});

test('separator validation prevents list commit', function (): void {
    $effects = 0;
    $separator = C::text(',')->validateSemantic(static fn(): string => 'Rejected separator');
    $result = C::text('a')->separatedBy($separator)->onCommit(static function () use (&$effects): void {
        ++$effects;
    })->parse('a,a');
    $this->assertSame(['Rejected separator'], $result->validate()->errors);
    $this->assertFalse($result->commit());
    $this->assertSame(0, $effects);
});

test('discarded pairs do not commit or validate and frozen dependencies stay detached', function (): void {
    $separator = (new Text(','))->validateSemantic(static fn(): string => 'discarded');
    $item = new Sequence(new Text('a'));
    $list = new SeparatedList($item, $separator);
    $frozen = $list->freeze();
    $item->add(new Text('b'));
    $result = $frozen->matchPrefix('a,x');
    $this->assertSame(['a'], $result->value());
    $this->assertTrue($result->validate()->valid);
    $this->assertTrue($result->commit());
    $this->assertFalse($list->parseAll(new ByteSource('a'))->success);
    $this->assertSame(['ab'], $list->parseAll(new ByteSource('ab'))->value());
});

test('list restores spacing scope after an operand throws', function (): void {
    $source = new ByteSource('a');
    $session = new ParseSession($source);
    $throwing = (new Text('a'))->guard(static function (): never {
        throw new RuntimeException('guard');
    });
    $list = (new SeparatedList($throwing, new Text(',')))->spacing(new Text(' '));
    try {
        $list->recognizeInSession($source, 0, $session);
        $this->fail('Expected guard exception');
    } catch (RuntimeException $error) {
        $this->assertSame('guard', $error->getMessage());
    }
    $spacing = null;
    $this->assertSame(0, $session->skipSpacing($source, 0, $spacing));
    $this->assertNull($spacing);
});

test('list caches mapping errors independently for each recognition', function (): void {
    $calls = 0;
    $error = new RuntimeException('bad item');
    $list = C::text('a')->mapText(static function () use (&$calls, $error): never {
        ++$calls;
        throw $error;
    })->separatedBy(',');
    $result = $list->parse('a,a');
    for ($read = 0; $read < 2; ++$read) {
        try {
            $result->value();
            $this->fail('Expected mapping error');
        } catch (RuntimeException $caught) {
            $this->assertSame($error, $caught);
        }
    }
    $this->assertSame(1, $calls);
    try {
        $list->parse('a')->value();
    } catch (RuntimeException $caught) {
        $this->assertSame($error, $caught);
    }
    $this->assertSame(2, $calls);
});

test('list honors inherited spacing local overrides and lexemes', function (): void {
    $list = C::text('a')->separatedBy(',');
    $this->assertSame(['a', 'a'], $list->parse('a , a', spacing: C::text(' '))->value());
    $this->assertFalse($list->spacing(null)->parse('a , a', spacing: C::text(' '))->success);
    $this->assertFalse($list->lexeme()->parse('a , a', spacing: C::text(' '))->success);
    $this->assertSame(['a', 'a'], $list->parse('a,a')->value());
    $this->assertSame($list->freeze(), $list->freeze());
});

test('list delegates through cooperative parse limits', function (): void {
    $this->expectException(ParseLimitException::class);
    C::text('a')->separatedBy(',')->parse('a,a,a', limits: new ParseLimits(maxWork: 2));
});
