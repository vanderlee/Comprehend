<?php

namespace Tests\Src\Core;

use RuntimeException;
use Tests\ParserTestCase;
use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Directive\Space;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Not;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;

/**
 * @group core
 */
class SpacingEffectsTest extends ParserTestCase
{
    /**
     * @dataProvider failedPathData
     */
    public function testFailedPathsDoNotResolveSpacing($build, $input)
    {
        $calls = 0;
        $assigned = 'unchanged';
        $spacer = (new Text(' '))->assignTo($assigned)->callback(function () use (&$calls) {
            $calls++;
        });
        $result = $build($spacer)->match($input);
        $this->assertFalse($result->match);
        $this->assertSame(0, $calls);
        $this->assertSame('unchanged', $assigned);
    }

    public function failedPathData()
    {
        return [
            'sequence fails after spacing' => [function ($spacer) {
                return (new Sequence('a', 'b'))->spacing($spacer);
            }, 'a c'],
            'repeat misses minimum' => [function ($spacer) {
                return (new Repeat('a', 3))->spacing($spacer);
            }, 'a a c'],
            'accepted repeat in failing parent' => [function ($spacer) {
                return new Sequence((new Repeat('a', 2, 2))->spacing($spacer), 'b');
            }, 'a a c'],
            'successful probe makes negative lookahead fail' => [function ($spacer) {
                return new Not((new Sequence('a', 'b'))->spacing($spacer));
            }, 'a b'],
        ];
    }

    /**
     * @dataProvider choiceData
     */
    public function testOnlySelectedAlternativeResolvesSpacing($preference)
    {
        $events = [];
        $makeSpacer = function ($name) use (&$events) {
            return (new Text(' '))->callback(function () use ($name, &$events) {
                $events[] = $name;
            });
        };
        $failed = (new Sequence('a', 'x'))->spacing($makeSpacer('failed'));
        $short = (new Sequence('a', 'b'))->spacing($makeSpacer('short'));
        $long = (new Sequence('a', 'bc'))->spacing($makeSpacer('long'));
        $result = (new Choice($failed, $short, $long))->setPreference($preference)->match('a bc');
        $longest = $preference === 'longest';
        $this->assertResult(true, $longest ? 4 : 3, $result);
        $this->assertSame([$longest ? 'long' : 'short'], $events);
    }

    public function choiceData()
    {
        return [['first'], ['longest'], ['shortest']];
    }

    public function testSuccessfulSequenceCallbacksRunInSourceOrderAfterRecognition()
    {
        $events = [];
        $assigned = 'unchanged';
        $spacer = (new Text(' '))->assignTo($assigned)->token('Spacing')->setResult('spacing')
            ->callback(function ($text, $input, $offset, $length) use (&$events) {
                $events[] = ['space', $text, $input, $offset, $length];
            });
        $first = (new Text('a'))->setResult('first')->callback(function () use (&$events) {
            $events[] = 'a';
        });
        $second = new class('b', $events, $assigned) extends Text {
            private $events;
            private $assigned;

            public function __construct($text, &$events, &$assigned)
            {
                parent::__construct($text);
                $this->events = &$events;
                $this->assigned = &$assigned;
            }

            protected function parse(&$input, $offset, Context $context)
            {
                if ($this->events !== [] || $this->assigned !== 'unchanged') {
                    throw new RuntimeException('Effects ran during recognition');
                }
                return parent::parse($input, $offset, $context);
            }
        };
        $second->setResult('second')->callback(function () use (&$events, &$assigned) {
            $events[] = ['b', $assigned];
        });
        $parser = (new Sequence($first, $second))->spacing($spacer)->callback(function () use (&$events) {
            $events[] = 'root';
        });
        $result = $parser->match('za b', 1);
        $this->assertResult(true, 3, $result);
        $this->assertSame(['a', ['space', ' ', 'za b', 2, 1], ['b', ' '], 'root'], $events);
        $this->assertSame(['first' => 'a', 'second' => 'b'], $result->results);
        $this->assertSame(['a', 'b'], array_map(function ($token) { return $token->text; }, $result->token->children));
        $this->assertCount(4, $events, 'Reading outputs must not repeat spacing effects');
    }

    public function testRepeatDiscardsSpacingBeforeFailedIteration()
    {
        $offsets = [];
        $spacer = (new Text(' '))->callback(function ($text, $input, $offset) use (&$offsets) {
            $offsets[] = $offset;
        });
        $result = (new Repeat('a'))->spacing($spacer)->match('a a c');
        $this->assertResult(true, 3, $result);
        $this->assertSame([1], $offsets);
        $this->assertCount(2, $result->token->children);
    }

    public function testNestedSpacerEffectsAreDeferredWithOwningSpacer()
    {
        $events = [];
        $inner = (new Text('-'))->callback(function () use (&$events) { $events[] = 'inner'; });
        $outer = (new Sequence(' ', ' '))->spacing($inner)
            ->callback(function () use (&$events) { $events[] = 'outer'; });
        $parser = (new Sequence('a', 'b'))->spacing($outer);
        $this->assertResult(false, 4, $parser->match('a - c'));
        $this->assertSame([], $events);
        $this->assertResult(true, 5, $parser->match('a - b'));
        $this->assertSame(['inner', 'outer'], $events);
    }

    public function testExceptionAfterSpacingDoesNotResolveEffects()
    {
        $calls = 0;
        $spacer = (new Text(' '))->callback(function () use (&$calls) { $calls++; });
        $child = new class('b') extends Text {
            protected function parse(&$input, $offset, Context $context)
            {
                throw new RuntimeException('Child exception');
            }
        };
        try {
            (new Sequence('a', $child))->spacing($spacer)->match('a b');
            $this->fail('Expected child exception');
        } catch (RuntimeException $exception) {
            $this->assertSame('Child exception', $exception->getMessage());
        }
        $this->assertSame(0, $calls);
    }

    public function testSuccessfulZeroLengthSpacerStillResolvesCallback()
    {
        $calls = 0;
        $spacer = Repeat::optional(' ')->callback(function () use (&$calls) { $calls++; });
        $this->assertResult(true, 2, (new Space($spacer, new Sequence('a', 'b')))->match('ab'));
        $this->assertSame(1, $calls);
    }

    public function testProcessedRuleRetainsOnlySelectedEffects()
    {
        $events = [];
        $spacer = (new Text(' '))->callback(function () use (&$events) { $events[] = 'space'; });
        $child = (new Text('b'))->setResult('child')
            ->callback(function () use (&$events) { $events[] = 'child'; });
        $definition = new Definition((new Sequence('a', $child))->spacing($spacer));
        $definition->addProcessor('processed', function ($results) { return strtoupper($results['child']); });
        $rule = $definition->build()->callback(function () use (&$events) { $events[] = 'rule'; });

        $this->assertResult(false, 3, (new Sequence($rule, '!'))->match('a b?'));
        $this->assertSame([], $events);
        $result = $rule->match('a b');
        $this->assertResult(true, 3, $result);
        $this->assertSame(['space', 'child', 'rule'], $events);
        $this->assertSame(['processed' => 'B'], $result->results);
        $this->assertSame([], $result->token->children);
    }
}
