<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Builder\FreezeFlags;
use Vanderlee\Comprehend\Builder\FrozenGrammar;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Directive\CaseSensitive;
use Vanderlee\Comprehend\Directive\Space;
use Vanderlee\Comprehend\Directive\Trivia;
use Vanderlee\Comprehend\Extensions\Optimizer\GraphOptimizer;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadWhileRange;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadWhileSet;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\SeparatedList;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Set;
use Vanderlee\Comprehend\Parser\Terminal\Regex;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseMetrics;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\Utf8FileSource;
use Vanderlee\Comprehend\Source\Utf8Source;
use Vanderlee\Comprehend\Tree\ConcreteNode;

test('prepared sequences retain trivia nodes without entering temporary wrapper records', function (
    Parser $spacing,
): void {
    $parser = new Space($spacing, new Sequence(new Text('a'), new Regex('/b/A'), new Text('c')));
    $source = new Utf8Source('ab  c');
    $expected = $parser->freeze(FreezeFlags::NONE)->parse($source);
    $grammar = $parser->freeze();
    $root = (new ReflectionProperty(FrozenGrammar::class, 'root'))->getValue($grammar);
    $metrics = new ParseMetrics();
    $session = new ParseSession($source, metrics: $metrics);
    $result = new ParseResult($root->recognizeInSession($source, 0, $session));

    expect($result->node)->toEqual($expected->node)
        ->and($result->ast())->toEqual($expected->ast())
        ->and($result->diagnostic)->toEqual($expected->diagnostic)
        ->and($result->value())->toBe($expected->value())
        // Space, Sequence and its three operands; both trivia wrappers remain in the CST only.
        ->and($metrics->snapshot()['parser_entries'])->toBe(5);
})->with([[new ReadWhileSet(' ')], [Repeat::star(new Text(' '))]]);

test('syntax only trivia preserves rollback and partial failure across prepared loops', function (
    Closure $build,
    array $inputs,
): void {
    foreach ([new ReadWhileSet(" \t"), Repeat::star(new Set(" \t"))] as $spacing) {
        $parser = new Space($spacing, $build());
        $plain = $parser->freeze(FreezeFlags::NONE);
        $prepared = $parser->freeze();
        foreach ($inputs as $input) {
            foreach ([new ByteSource('>' . $input), new Utf8Source('>' . $input)] as $source) {
                foreach (['matchPrefix', 'parseAll'] as $method) {
                    for ($pass = 0; $pass < 2; ++$pass) {
                        $expected = $plain->$method($source, 1);
                        $actual = $prepared->$method($source, 1);
                        expect($actual->success)->toBe($expected->success)
                            ->and($actual->consumedByteLength)->toBe($expected->consumedByteLength)
                            ->and($actual->node)->toEqual($expected->node)
                            ->and($actual->diagnostic)->toEqual($expected->diagnostic)
                            ->and($actual->farthestFailure)->toBe($expected->farthestFailure)
                            ->and($actual->ast())->toEqual($expected->ast())
                            ->and($actual->value())->toBe($expected->value());
                    }
                }
            }
        }
    }
})->with([
    'sequence' => [static fn(): Parser => new Sequence(new Text('a'), new Text('bc'), new Text('d')),
        ['abcd', 'a bc  d', "a\tbc d", 'a bx', 'a bc ', 'a bc d!', 'a bc d ']],
    'repeat' => [static fn(): Parser => new Repeat(new Text('ab'), 1, 3),
        ['ab', 'abab', 'ab ab  ab', 'ab ax', 'ab ab ', 'ab ab ab ab', 'ab ab!', 'ax']],
    'list' => [static fn(): Parser => new SeparatedList(new Text('ab'), new Text('::'), 0, 3, true, true),
        ['', 'ab', 'ab::ab', '::ab :: ab ::', 'ab :: ax', 'ab :: ', 'ab :: ab :: ab :: ab', 'ab!']],
]);

test('syntax only trivia preserves collection caching validation and explicit effect order', function (
    bool $repeat,
): void {
    $observations = [];
    foreach ([FreezeFlags::NONE, FreezeFlags::OPTIMIZE] as $flags) {
        $events = [];
        $item = (new Text('a'))->pushResult('items', static function (string $text) use (&$events): string {
            $events[] = 'collect';

            return strtoupper($text);
        })->validateSemantic(static function (ParseResult $result) use (&$events): bool {
            $events[] = 'validate:' . $result->start;

            return true;
        })->onCommit(static function (ParseResult $result) use (&$events): void {
            $events[] = 'commit:' . $result->start;
        });
        $body = $repeat ? Repeat::exact($item, 3) : new Sequence($item, $item, $item);
        $body->mapValue(static fn(ParseResult $result): object => (object) $result->results());
        $parser = new Space(new ReadWhileSet(' '), $body);
        $result = $parser->freeze($flags)->parse('a a  a');
        expect($result->success)->toBeTrue()->and($events)->toBe([]);
        $value = $result->value();
        expect($result->value())->toBe($value)->and($value->items)->toBe(['A', 'A', 'A'])
            ->and($events)->toBe(['collect', 'collect', 'collect']);
        expect($result->commit())->toBeTrue()->and($result->commit())->toBeTrue();
        $observations[] = [$result->node->toArray(), $result->results(), $events];
    }
    expect($observations[1])->toBe($observations[0]);
})->with([false, true]);

test('custom list separator hooks still receive preceding trivia execution records', function (): void {
    $list = new class (new Text('a'), new Text(',')) extends SeparatedList {
        protected function recognizeSeparator(
            Parser $parser,
            Source $source,
            int $offset,
            ParseSession $session,
            ?Success $spacing,
        ): AbstractMatch|ConcreteNode {
            expect($spacing)->not->toBeNull()->and($spacing->node->isTrivia)->toBeTrue();

            return parent::recognizeSeparator($parser, $source, $offset, $session, $spacing);
        }
    };
    $parser = new Space(new ReadWhileSet(' '), $list);
    expect($parser->freeze()->parse('a , a')->value())->toBe(['a', 'a']);
});

test('syntax only sequence and repeat trivia retain every exhausted recognition limit', function (bool $repeat): void {
    $body = $repeat ? Repeat::exact(new Text('a'), 3) : new Sequence(new Text('a'), new Text('a'), new Text('a'));
    $parser = new Space(new ReadWhileSet(' '), $body);
    $plain = $parser->freeze(FreezeFlags::NONE);
    $prepared = $parser->freeze();
    $source = new Utf8Source('a a  a');
    $budgets = [
        ...array_map(static fn(int $n): ParseLimits => new ParseLimits(maxWork: $n), range(1, 45)),
        ...array_map(static fn(int $n): ParseLimits => new ParseLimits(maxDepth: $n), range(1, 8)),
    ];
    foreach ($budgets as $limits) {
        $observations = [];
        foreach ([$plain, $prepared] as $grammar) {
            try {
                $result = $grammar->parse($source, limits: $limits);
                $observations[] = [$result->success, $result->node?->toArray(), $result->diagnostic?->toArray()];
            } catch (ParseLimitException $error) {
                $observations[] = $error->getMessage();
            }
        }
        expect($observations[1])->toBe($observations[0]);
    }
})->with([false, true]);

test('prepared trivia and direct list separators preserve syntax values diagnostics and offsets', function (
    Parser $spacing,
): void {
    $item = (new Text('a'))->mapValue(static fn(): int => 7);
    $list = (new SeparatedList($item, new Text(',')))->name('items')->tag('list');
    $parser = (new Sequence(new Text('['), $list, new Text(']')))->spacing($spacing);
    $plain = $parser->freeze(FreezeFlags::NONE);
    $optimized = $parser->freeze();
    foreach (['[]', '[a]', '[ a , a ]', '[a,a]', '[a,]', '[a,,a]', '[?]', '[a]!', '[a', '[aé]'] as $text) {
        foreach ([new ByteSource('>' . $text), new Utf8Source('>' . $text)] as $source) {
            for ($pass = 0; $pass < 2; ++$pass) {
                foreach (['parseAll', 'matchPrefix'] as $operation) {
                    $expected = $plain->$operation($source, 1);
                    $actual = $optimized->$operation($source, 1);
                    expect($actual->success)->toBe($expected->success)
                        ->and($actual->consumedByteLength)->toBe($expected->consumedByteLength)
                        ->and($actual->node)->toEqual($expected->node)
                        ->and($actual->diagnostic)->toEqual($expected->diagnostic)
                        ->and($actual->ast())->toEqual($expected->ast())
                        ->and($actual->value())->toBe($expected->value());
                }
            }
        }
    }
})->with([
    'set scanner' => [new ReadWhileSet(" \t\r\n")],
    'range scanner' => [new ReadWhileRange(' ', ' ')],
    'repeated characters' => [Repeat::star(new Set(" \t\r\n"))],
    'bounded required scanner' => [new ReadWhileSet(' ', 1, 2)],
]);

test('prepared trivia reduces dispatch and retains zero width and repeated character nodes', function (): void {
    foreach ([new ReadWhileSet(' '), Repeat::star(new Text(' '))] as $spacing) {
        $parser = (new Sequence(new Text('a'), new Text('b'), new Text('c')))->spacing($spacing);
        $source = new Utf8Source('ab  c');
        $trees = [];
        $counts = [];
        foreach ([FreezeFlags::NONE, FreezeFlags::OPTIMIZE] as $flags) {
            $grammar = $parser->freeze($flags);
            $root = (new ReflectionProperty(FrozenGrammar::class, 'root'))->getValue($grammar);
            $metrics = new ParseMetrics();
            $match = $root->recognizeInSession($source, 0, new ParseSession($source, metrics: $metrics));
            $trees[] = $match->node;
            $counts[] = $metrics->snapshot()['parser_entries'];
        }
        expect($trees[1])->toEqual($trees[0])
            ->and($trees[1]->children[1]->isTrivia)->toBeTrue()
            ->and($trees[1]->children[1]->span->byteLength())->toBe(0)
            ->and($trees[1]->children[3]->rawText())->toBe('  ')
            ->and($counts[1])->toBeLessThan($counts[0]);
    }
});

test('direct separators preserve list bounds and permitted boundary separators', function (
    int $min,
    ?int $max,
    bool $leading,
    bool $trailing,
): void {
    $parser = (new SeparatedList(new Text('a'), new Text('::'), $min, $max, $leading, $trailing))
        ->spacing(new ReadWhileSet(' '));
    foreach (['', 'a', 'a::a', '::a', 'a::', '::a::', '::', 'a::a::a', 'a :: a ::', 'a :x'] as $text) {
        $source = new Utf8Source($text);
        $expected = $parser->freeze(FreezeFlags::NONE)->parse($source);
        $actual = $parser->freeze()->parse($source);
        expect($actual->success)->toBe($expected->success)
            ->and($actual->node)->toEqual($expected->node)
            ->and($actual->diagnostic)->toEqual($expected->diagnostic)
            ->and($actual->value())->toBe($expected->value());
    }
})->with([[0, null, false, false], [1, 2, true, true], [0, 0, true, true], [2, 2, false, true]]);

test('list specialization retains spacing effects validation collections and item ordering', function (): void {
    $observations = [];
    foreach ([FreezeFlags::NONE, FreezeFlags::OPTIMIZE] as $flags) {
        $events = [];
        $spacing = (new ReadWhileSet(' '))->setResult('spacing', 'excluded')
            ->validateSemantic(static function () use (&$events): bool {
                $events[] = 'spacing-validation';

                return true;
            })->onCommit(static function (ParseResult $result) use (&$events): void {
                $events[] = 'space:' . $result->start;
            });
        $item = (new Text('a'))->pushResult('items', 'a')
            ->mapValue(static fn(): object => (object) ['value' => 7])
            ->onCommit(static function (ParseResult $result) use (&$events): void {
                $events[] = 'item:' . $result->start;
            });
        $parser = (new SeparatedList($item, new Text(',')))->spacing($spacing);
        $result = $parser->freeze($flags)->parse('a , a');
        expect($result->success)->toBeTrue()->and($events)->toBe([]);
        $values = $result->value();
        expect($result->value())->toBe($values)->and($result->results())->toBe(['items' => ['a', 'a']]);
        $result->commit();
        $result->commit();
        $observations[] = [$result->node->toArray(), $values, $events];
    }
    expect($observations[1])->toEqual($observations[0]);
});

test('separator failures never replay preceding executable trivia', function (): void {
    foreach ([FreezeFlags::NONE, FreezeFlags::OPTIMIZE] as $flags) {
        $calls = 0;
        $spacing = (new ReadWhileSet(' '))->guard(static function () use (&$calls): bool {
            ++$calls;

            return true;
        });
        $result = (new SeparatedList(new Text('a'), new Text('::')))->spacing($spacing)
            ->freeze($flags)->parse('a :x');
        expect($result->success)->toBeFalse()->and($calls)->toBe(1)
            ->and($result->diagnostic->byteOffset)->toBe(3);
    }
});

test('trivia and separator metadata custom classes unicode and case scopes retain ordinary behavior', function (
    Parser $separator,
    Parser $spacing,
    string $text,
): void {
    $parser = new CaseSensitive(false, (new SeparatedList(new Text('a'), $separator))->spacing($spacing));
    $expected = $parser->freeze(FreezeFlags::NONE)->parse($text);
    $actual = $parser->freeze()->parse($text);
    expect($actual->success)->toBe($expected->success)
        ->and($actual->node)->toEqual($expected->node)
        ->and($actual->diagnostic)->toEqual($expected->diagnostic)
        ->and($actual->value())->toBe($expected->value());
})->with([
    [(new Text(','))->name('separator')->tag('punctuation'), new ReadWhileSet(' '), 'a , a'],
    [new Text(','), (new ReadWhileSet(' '))->name('space'), 'a , a'],
    [new Text('X'), new ReadWhileSet(' '), 'a x a'],
    [new Text('é'), new ReadWhileSet(' '), 'a é a'],
    [new Text(','), new ReadWhileSet('é'), 'aé,éa'],
    [new class (',') extends Text {
        public function getText(): string
        {
            throw new LogicException('Custom separator configuration must not be classified');
        }
    }, new ReadWhileSet(' '), 'a,a'],
    [new Text(','), Repeat::star(new class (' ') extends Set {
        public function getCharacters(): string
        {
            throw new LogicException('Custom trivia configuration must not be classified');
        }
    }), 'a , a'],
]);

test('optimized trivia and separators preserve every exhausted work and depth budget', function (
    Parser $spacing,
): void {
    $parser = (new SeparatedList(new Text('a'), new Text(',')))->spacing($spacing);
    $source = new Utf8Source('a , a');
    $budgets = [
        ...array_map(static fn(int $n): ParseLimits => new ParseLimits(maxWork: $n), range(1, 35)),
        ...array_map(static fn(int $n): ParseLimits => new ParseLimits(maxDepth: $n), range(1, 8)),
    ];
    foreach ($budgets as $limits) {
        $observations = [];
        foreach ([FreezeFlags::NONE, FreezeFlags::OPTIMIZE] as $flags) {
            try {
                $result = $parser->freeze($flags)->parse($source, limits: $limits);
                $observations[] = [$result->success, $result->node?->toArray()];
            } catch (ParseLimitException $error) {
                $observations[] = $error->getMessage();
            }
        }
        expect($observations[1])->toBe($observations[0]);
    }
})->with([[new ReadWhileSet(' ')], [Repeat::star(new Text(' '))]]);

test('discarded lists never evaluate item mappings or commit their effects', function (): void {
    $events = [];
    $item = (new Text('a'))->mapValue(static function () use (&$events): never {
        $events[] = 'value';
        throw new LogicException('Discarded value must not be evaluated');
    })->onCommit(static function () use (&$events): void {
        $events[] = 'commit';
    });
    $list = (new SeparatedList($item, new Text(',')))->spacing(new ReadWhileSet(' '));
    $parser = Choice::first(new Sequence($list, new Text('!')), new Text('a,a?'))->freeze();
    $result = $parser->parse('a,a?');
    expect($result->success)->toBeTrue()->and($result->value())->toBe('a,a?')
        ->and($result->commit())->toBeTrue()->and($events)->toBe([]);
});

test('explicit trivia metadata stays lazy and ordinary authoring copies clear prepared plans', function (): void {
    $events = [];
    $trivia = (new Trivia(new ReadWhileSet(' ')))->name('gap')->tag('spacing')
        ->mapValue(static function (ParseResult $result) use (&$events): string {
            $events[] = 'value';

            return $result->rawText();
        })->onCommit(static function () use (&$events): void {
            $events[] = 'commit';
        });
    $snapshot = new GraphSnapshot(optimize: true);
    $copy = $snapshot->copy($trivia);
    (new GraphOptimizer())->prepare($copy);
    $ordinary = (new GraphSnapshot())->copy($copy);
    $expected = $trivia->parseAll(new Utf8Source('  '));
    foreach ([$copy, $ordinary] as $parser) {
        $result = $parser->parseAll(new Utf8Source('  '));
        expect($result->node->toArray())->toBe($expected->node->toArray())->and($events)->toBe([]);
        expect($result->value())->toBe('  ')->and($result->value())->toBe('  ');
        $result->commit();
        $result->commit();
        expect($events)->toBe(['value', 'commit']);
        $events = [];
    }
});

test('prepared trivia and list nodes bind to each file snapshot and reconstruct its source', function (): void {
    $stream = tmpfile();
    fwrite($stream, 'a , a');
    $source = new Utf8FileSource($stream);
    try {
        $parser = (new SeparatedList(new Text('a'), new Text(',')))->spacing(new ReadWhileSet(' '));
        $expected = $parser->freeze(FreezeFlags::NONE)->parse($source);
        $actual = $parser->freeze()->parse($source);
        expect($actual->success)->toBeTrue()->and($actual->node)->toEqual($expected->node)
            ->and($actual->rawText())->toBe('a , a')->and($actual->value())->toBe(['a', 'a']);
    } finally {
        $source->close();
        fclose($stream);
    }
});

test('throwing prepared trivia restores the session spacing scope', function (string $method): void {
    $grammar = (new ReadWhileSet("\xff"))->freeze();
    $root = (new ReflectionProperty(FrozenGrammar::class, 'root'))->getValue($grammar);
    $source = new Utf8Source(' ');
    $session = new ParseSession($source, $root);
    try {
        $session->$method($source, 0, $match);
        throw new LogicException('Invalid UTF-8 trivia must fail');
    } catch (InvalidArgumentException $error) {
        expect($error->getMessage())->toBe('Source must contain valid UTF-8');
    }
    expect($match)->toBeNull()->and($session->isLexeme())->toBeFalse();
    $session->pushSpacer(new Text(' '));
    expect($session->$method($source, 0))->toBe(1);
})->with(['skipSpacing', 'skipSpacingSyntax']);
