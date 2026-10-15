<?php

declare(strict_types=1);

namespace Tests\features\semantics;

use RuntimeException;
use Tests\ParserTestCase;
use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Parser\Structure\All;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Tree\ConcreteNode;
use Vanderlee\Comprehend\Source\Utf8Source;
use Vanderlee\Comprehend\Match\Success;

uses(ParserTestCase::class);

test('plain parent matches retain child collections and effects without local registrations', function () {
    $events = [];
    $assigned = null;
    $child = (new Text('a'))->setResult('letter')->assignTo($assigned)->onCommit(
        function () use (&$events) {
            $events[] = 'child';
        },
    );
    $parser = new Sequence(new Text('('), $child, new Text(')'));
    $plain = $parser->parseAll(new Utf8Source('(a)'));
    // Later registrations must not affect a result already recognized.
    $parser->setResult('later')->onCommit(function () use (&$events) {
        $events[] = 'parent';
    });
    $this->assertSame(['letter' => 'a'], $plain->results());
    $this->assertNull($assigned);
    $this->assertSame([], $events);
    $this->assertTrue($plain->commit());
    $this->assertTrue($plain->commit());
    $this->assertSame('a', $assigned);
    $this->assertSame(['child'], $events);

    $registered = $parser->parseAll(new Utf8Source('(a)'));
    $this->assertSame(['letter' => 'a', 'later' => '(a)'], $registered->results());
    $this->assertTrue($registered->commit());
    $this->assertSame(['child', 'child', 'parent'], $events);
});

test('guard rejects candidate without semantic or committed effects', function () {
    $events = [];
    $maps = 0;
    $rejected = (new Text('a'))->guard(fn(ConcreteNode $node): bool => $node->rawText() === 'b')->setResult(
        null,
        function () use (&$maps) {
            ++$maps;

            return 'mapped';
        },
    )->onCommit(
        function () use (&$events) {
            $events[] = 'rejected';
        },
    );
    $accepted = (new Text('a'))->onCommit(
        function () use (&$events) {
            $events[] = 'accepted';
        },
    );
    $result = Choice::first($rejected, $accepted)->parseAll(new Utf8Source('a'));
    $this->assertTrue($result->success);
    $this->assertSame([], $events);
    $this->assertSame(0, $maps);
    $this->assertTrue($result->commit());
    $this->assertTrue($result->commit());
    $this->assertSame(['accepted'], $events);
    $this->assertSame(0, $maps);
    $failure = $rejected->parseAll(new Utf8Source('a'));
    $this->assertFalse($failure->success);
    $this->assertSame(['guard(Text)'], $failure->diagnostic->expected);
});

test('semantic validation follows syntax and blocks commit', function () {
    $events = [];
    $parser = (new Text('42'))
        ->mapValue(fn(ParseResult $result): int => (int) $result->rawText())
        ->validateSemantic(
            function (ParseResult $result) use (&$events) {
                $events[] = 'validate';

                return $result->value() < 10 ? true : 'Number is too large';
            },
        )->onCommit(
            function () use (&$events) {
                $events[] = 'effect';
            },
        );
    $failed = $parser->parseAll(new Utf8Source('42x'));
    $this->assertFalse($failed->commit());
    $this->assertSame([], $events);
    $result = $parser->parseAll(new Utf8Source('42'));
    $this->assertTrue($result->success);
    $this->assertFalse($result->isCommitted());
    $this->assertSame([], $events);
    $report = $result->validate();
    $this->assertFalse($report->valid);
    $this->assertSame(['Number is too large'], $report->errors);
    $this->assertSame($report, $result->validate());
    $this->assertFalse($result->commit());
    $this->assertSame(['validate'], $events);
    $this->assertSame('42', $result->node->rawText());
});

test('selected child validation and effects run in order once', function () {
    $events = [];
    $assigned = 'before';
    $child = (new Text('a'))->assignTo($assigned)->validateSemantic(
        function () use (&$events) {
            $events[] = 'child validation';

            return true;
        },
    )->onCommit(
        function () use (&$events) {
            $events[] = 'child';
        },
    );
    $spacer = (new Text(' '))->onCommit(
        function () use (&$events) {
            $events[] = 'space';
        },
    );
    $parser = (new Sequence($child, new Text('b')))->spacing($spacer)->validateSemantic(
        function () use (&$events) {
            $events[] = 'root validation';

            return true;
        },
    )->onCommit(
        function () use (&$events) {
            $events[] = 'root';
        },
    );
    $result = $parser->parseAll(new Utf8Source('a b'));
    $this->assertSame('before', $assigned);
    $this->assertTrue($result->commit());
    $this->assertTrue($result->isCommitted());
    $this->assertSame('a', $assigned);
    $this->assertSame(['child validation', 'root validation', 'child', 'space', 'root'], $events);
    $this->assertTrue($result->commit());
    $this->assertCount(5, $events);
});

test('abandoned definitions do not evaluate transforms processors or validators', function () {
    $events = [];
    $definition = new Definition(
        (new Text('a'))->setResult(
            'item',
            function () use (&$events) {
                $events[] = 'transform';

                return 'value';
            },
        ),
        function () use (&$events) {
            $events[] = 'validate';

            return true;
        },
    );
    $definition->addProcessor(
        'value',
        function () use (&$events) {
            $events[] = 'process';

            return 42;
        },
    );
    $abandoned = new Sequence($definition->implementation(), new Text('x'));
    $parser = Choice::first($abandoned, new Text('ab'));
    $result = $parser->parseAll(new Utf8Source('ab'));
    $this->assertTrue($result->commit());
    $this->assertSame([], $events);
    $this->assertFalse($definition->build()->parseAll(new Utf8Source('ab'))->commit());
    $this->assertSame([], $events);
    $selected = $definition->build()->parseAll(new Utf8Source('a'));
    $this->assertSame([], $events);
    $this->assertSame(['value' => 42], $selected->results());
    $this->assertSame(['transform', 'process'], $events);
    $this->assertTrue($selected->commit());
    $this->assertSame(['transform', 'process', 'validate'], $events);
});

test('snapshots exclude later validators callbacks and assignments', function () {
    $events = [];
    $first = 'before';
    $later = 'later';
    $parser = (new Text('a'))->assignTo($first)->validateSemantic(fn() => true);
    $result = $parser->parseAll(new Utf8Source('a'));
    $parser->assignTo($later)->validateSemantic(fn() => false)->onCommit(
        function () use (&$events) {
            $events[] = 'late';
        },
    );
    $this->assertTrue($result->commit());
    $this->assertSame('a', $first);
    $this->assertSame('later', $later);
    $this->assertSame([], $events);
    $this->assertFalse($parser->parseAll(new Utf8Source('a'))->commit());
});

test('failed effect sequence is not retried and cannot claim committed', function () {
    $calls = 0;
    $failure = new RuntimeException('effect failed');
    $result = (new Text('a'))->onCommit(
        function () use (&$calls, $failure) {
            ++$calls;
            throw $failure;
        },
    )->parseAll(new Utf8Source('a'));
    for ($i = 0; $i < 2; ++$i) {
        try {
            $result->commit();
            $this->fail('Expected retained effect error');
        } catch (RuntimeException $error) {
            $this->assertSame($failure, $error);
        }
    }
    $this->assertSame(1, $calls);
    $this->assertFalse($result->isCommitted());
});

test('failing trivia effect stops later effects without replaying earlier effects', function (): void {
    $events = [];
    $failure = new RuntimeException('trivia effect failed');
    $first = (new Text('a'))->onCommit(static function () use (&$events): void {
        $events[] = 'first';
    });
    $second = (new Text('b'))->onCommit(static function () use (&$events): void {
        $events[] = 'second';
    });
    $trivia = (new Text(' '))->onCommit(static function () use (&$events, $failure): void {
        $events[] = 'trivia';
        throw $failure;
    });
    $parser = (new Sequence($first, $second))->spacing($trivia)
        ->onCommit(static function () use (&$events): void {
            $events[] = 'parent';
        });
    $result = $parser->parseAll(new Utf8Source('a b'));

    $this->assertSame([], $events);
    for ($attempt = 0; $attempt < 2; ++$attempt) {
        try {
            $result->commit();
            $this->fail('Expected the retained trivia effect failure');
        } catch (RuntimeException $error) {
            $this->assertSame($failure, $error);
        }
    }

    $this->assertSame(['first', 'trivia'], $events);
    $this->assertFalse($result->isCommitted());
});

test('required intersection validation is retained', function () {
    $result = (new All(
        new Text('a'),
        (new Text('ab'))->validateSemantic(fn() => 'Intersection rejected'),
    ))->matchPrefix(new Utf8Source('ab'));
    $this->assertTrue($result->success);
    $this->assertSame(['Intersection rejected'], $result->validate()->errors);
    $this->assertFalse($result->commit());
});

test('validation errors are cached and prevent effects', function () {
    $calls = 0;
    $effects = 0;
    $failure = new RuntimeException('validator failed');
    $result = (new Text('a'))->validateSemantic(
        function () use (&$calls, $failure) {
            ++$calls;
            throw $failure;
        },
    )->onCommit(
        function () use (&$effects) {
            ++$effects;
        },
    )->parseAll(new Utf8Source('a'));
    for ($i = 0; $i < 2; ++$i) {
        try {
            $result->commit();
            $this->fail('Expected cached validation error');
        } catch (RuntimeException $error) {
            $this->assertSame($failure, $error);
        }
    }
    $this->assertSame(1, $calls);
    $this->assertSame(0, $effects);
});

test('recursive validation is rejected', function () {
    $result = (new Text('a'))
        ->validateSemantic(fn(ParseResult $result) => $result->validate()->valid)
        ->parseAll(new Utf8Source('a'));
    $this->expectException(\LogicException::class);
    $result->validate();
});

test('recursive commit is rejected without replaying effects', function () {
    $result = null;
    $calls = 0;
    $parser = (new Text('a'))->onCommit(
        function () use (&$result, &$calls) {
            ++$calls;
            $result->commit();
        },
    );
    $result = $parser->parseAll(new Utf8Source('a'));
    for ($i = 0; $i < 2; ++$i) {
        try {
            $result->commit();
            $this->fail('Expected recursive commit failure');
        } catch (\LogicException $error) {
            $this->assertSame('Recursive effect commit', $error->getMessage());
        }
    }
    $this->assertSame(1, $calls);
    $this->assertFalse($result->isCommitted());
});

test('shared child validation errors are collected once before parent errors', function (): void {
    $events = [];
    $source = new Utf8Source('');
    $child = new Success();
    $child->setSourceMetadata($source->span(0, 0), null);
    $child->addSemanticValidators([
        function () use (&$events) {
            $events[] = 'child';

            return 'Child rejected';
        },
    ]);
    $parent = new Success(0, [$child, $child]);
    $parent->setSourceMetadata($source->span(0, 0), null);
    $parent->addSemanticValidators([
        function () use (&$events) {
            $events[] = 'parent';

            return false;
        },
    ]);
    $result = new ParseResult($parent);

    $report = $result->validate();
    $this->assertFalse($report->valid);
    $this->assertSame(['Child rejected', 'Semantic validation failed'], $report->errors);
    $this->assertSame($report, $result->validate());
    $this->assertFalse($result->commit());
    $this->assertSame(['child', 'parent'], $events);
});
