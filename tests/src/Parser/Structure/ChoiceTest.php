<?php

namespace Tests\Src\Parser\Structure;

use Tests\ParserTestCase;
use TypeError;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ChoicePolicy;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;

abstract class ChoiceTest extends ParserTestCase
{
    public static function choiceData()
    {
        return [
            [new Choice(new Text('a'), new Text('b')), '', 0, false, 0],
            [new Choice(new Text('a'), new Text('b')), 'a', 0, true, 1],
            [new Choice(new Text('a'), new Text('b')), 'b', 0, true, 1],
            [new Choice(new Text('a'), new Text('b')), 'c', 0, false, 0],
            [new Choice(new Text('a'), new Text('b')), 'za', 0, false, 0],
            [new Choice(new Text('a'), new Text('b')), 'za', 1, true, 1],
            [(new Choice(new Text('a'), new Text('b')))->preferFirst(), '', 0, false, 0],
            [(new Choice(new Text('a'), new Text('b')))->preferFirst(), 'a', 0, true, 1],
            [(new Choice(new Text('a'), new Text('b')))->preferFirst(), 'b', 0, true, 1],
            [(new Choice(new Text('a'), new Text('b')))->preferFirst(), 'c', 0, false, 0],
            [(new Choice(new Text('a'), new Text('b')))->preferFirst(), 'za', 0, false, 0],
            [(new Choice(new Text('a'), new Text('b')))->preferFirst(), 'za', 1, true, 1],
            [(new Choice(new Text('a'), new Text('ab')))->preferFirst(), 'ab', 0, true, 1],
            [(new Choice(new Text('ab'), new Text('a')))->preferFirst(), 'ab', 0, true, 2],
            [(new Choice(new Text('abc'), new Text('aaa')))->preferFirst(), 'ab', 0, false, 2],
            [(new Choice(new Text('aaa'), new Text('abc')))->preferFirst(), 'ab', 0, false, 2],
            [(new Choice(new Text('a'), new Text('b')))->preferLongest(), '', 0, false, 0],
            [(new Choice(new Text('a'), new Text('b')))->preferLongest(), 'a', 0, true, 1],
            [(new Choice(new Text('a'), new Text('b')))->preferLongest(), 'b', 0, true, 1],
            [(new Choice(new Text('a'), new Text('b')))->preferLongest(), 'c', 0, false, 0],
            [(new Choice(new Text('a'), new Text('b')))->preferLongest(), 'za', 0, false, 0],
            [(new Choice(new Text('a'), new Text('b')))->preferLongest(), 'za', 1, true, 1],
            [(new Choice(new Text('a'), new Text('ab')))->preferLongest(), 'ab', 0, true, 2],
            [(new Choice(new Text('ab'), new Text('a')))->preferLongest(), 'ab', 0, true, 2],
            [(new Choice(new Text('abc'), new Text('aaa')))->preferLongest(), 'ab', 0, false, 2],
            [(new Choice(new Text('aaa'), new Text('abc')))->preferLongest(), 'ab', 0, false, 2],
            [(new Choice(new Text('a'), new Text('b')))->preferShortest(), '', 0, false, 0],
            [(new Choice(new Text('a'), new Text('b')))->preferShortest(), 'a', 0, true, 1],
            [(new Choice(new Text('a'), new Text('b')))->preferShortest(), 'b', 0, true, 1],
            [(new Choice(new Text('a'), new Text('b')))->preferShortest(), 'c', 0, false, 0],
            [(new Choice(new Text('a'), new Text('b')))->preferShortest(), 'za', 0, false, 0],
            [(new Choice(new Text('a'), new Text('b')))->preferShortest(), 'za', 1, true, 1],
            [(new Choice(new Text('a'), new Text('ab')))->preferShortest(), 'ab', 0, true, 1],
            [(new Choice(new Text('ab'), new Text('a')))->preferShortest(), 'ab', 0, true, 1],
            [(new Choice(new Text('abc'), new Text('aaa')))->preferShortest(), 'ab', 0, false, 1],
            [(new Choice(new Text('aaa'), new Text('abc')))->preferShortest(), 'ab', 0, false, 1],
            [
                (new Choice(new Text('ab'), new Text('a'), new Text('abc')))->setPreference(ChoicePolicy::FIRST),
                'abc',
                0,
                true,
                2,
            ],
            [
                (new Choice(new Text('ab'), new Text('a'), new Text('abc')))->setPreference(ChoicePolicy::SHORTEST),
                'abc',
                0,
                true,
                1,
            ],
            [
                (new Choice(new Text('ab'), new Text('a'), new Text('abc')))->setPreference(ChoicePolicy::LONGEST),
                'abc',
                0,
                true,
                3,
            ],
        ];
    }

    public static function preferenceData(): array
    {
        return [[ChoicePolicy::FIRST], [ChoicePolicy::LONGEST], [ChoicePolicy::SHORTEST]];
    }
}

uses(ChoiceTest::class)->group('structure', 'parser');

test('string policy is rejected without changing the selected policy', function (): void {
    $choice = Choice::longest(new Text('a'), new Text('ab'));
    try {
        $choice->setPreference('first');
        $this->fail('Choice policies must be enum cases');
    } catch (TypeError $error) {
        $this->assertStringContainsString(ChoicePolicy::class, $error->getMessage());
    }
    $this->assertSame('ab', $choice->matchPrefix(new ByteSource('ab'))->rawText());
});

test('clearing local policy inherits the session policy', function (): void {
    $source = new ByteSource('ab');
    $session = new ParseSession($source, preference: ChoicePolicy::LONGEST);
    $choice = Choice::first(new Text('a'), new Text('ab'));
    $this->assertSame(1, $choice->recognizeInSession($source, 0, $session)->length);

    $choice->setPreference(null);

    $this->assertSame(2, $choice->recognizeInSession($source, 0, $session)->length);
    $this->assertSame(ChoicePolicy::LONGEST, $session->getPreference());
});

test('ranked choices fail when all alternatives are removed', function (): void {
    foreach ([Choice::longest(new Text('a')), Choice::shortest(new Text('a'))] as $choice) {
        unset($choice[0]);
        $result = $choice->matchPrefix(new ByteSource('a'));

        $this->assertFalse($result->success);
        $this->assertSame(0, $result->consumedByteLength);
    }
});

test('empty', function () {
    $this->expectExceptionMessage('No arguments');
    new Choice();
});

test('choice', function (Choice $parser, $input, $offset, $match, $length) {
    $this->assertResult($match, $length, $parser->matchPrefix(new ByteSource($input), $offset), (string) $parser);
})->with(ChoiceTest::choiceData());

test('set result', function () {
    $parser = (new Choice(
        (new Text('a'))->setResult('valueA'),
        (new Text('b'))->setResult('valueB'),
    ))->setResult('choice');
    $match = $parser->matchPrefix(new ByteSource('a'));
    $this->assertTrue($match->success);
    $this->assertTrue(array_key_exists('choice', $match->results()));
    $this->assertEquals('a', $match->result('choice'));
    $this->assertTrue(array_key_exists('valueA', $match->results()));
    $this->assertEquals('a', $match->result('valueA'));
    $this->assertFalse(array_key_exists('valueB', $match->results()));
    $this->assertEquals(null, $match->result('valueB'));
    $match = $parser->matchPrefix(new ByteSource('b'));
    $this->assertTrue($match->success);
    $this->assertTrue(array_key_exists('choice', $match->results()));
    $this->assertEquals('b', $match->result('choice'));
    $this->assertFalse(array_key_exists('valueA', $match->results()));
    $this->assertEquals(null, $match->result('valueA'));
    $this->assertTrue(array_key_exists('valueB', $match->results()));
    $this->assertEquals('b', $match->result('valueB'));
    $match = $parser->matchPrefix(new ByteSource('c'));
    $this->assertFalse($match->success);
    $this->assertFalse(array_key_exists('choice', $match->results()));
    $this->assertEquals(null, $match->result('choice'));
    $this->assertFalse(array_key_exists('valueA', $match->results()));
    $this->assertEquals(null, $match->result('valueA'));
    $this->assertFalse(array_key_exists('valueB', $match->results()));
    $this->assertEquals(null, $match->result('valueB'));
});

test('assign to', function () {
    $a = $b = $choice = null;
    $parser = (new Choice((new Text('a'))->assignTo($a), (new Text('b'))->assignTo($b)))->assignTo($choice);
    $a = $b = $choice = null;
    $match = $parser->matchPrefix(new ByteSource('a'));
    $match->commit();
    $this->assertTrue($match->success);
    $this->assertEquals('a', $choice);
    $this->assertEquals('a', $a);
    $this->assertEquals(null, $b);
    $a = $b = $choice = null;
    $match = $parser->matchPrefix(new ByteSource('b'));
    $match->commit();
    $this->assertTrue($match->success);
    $this->assertEquals('b', $choice);
    $this->assertEquals(null, $a);
    $this->assertEquals('b', $b);
    $a = $b = $choice = null;
    $match = $parser->matchPrefix(new ByteSource('c'));
    $match->commit();
    $this->assertFalse($match->success);
    $this->assertEquals(null, $choice);
    $this->assertEquals(null, $a);
    $this->assertEquals(null, $b);
});

test('static constructors', function () {
    $c = Choice::first(new Text('aa'), new Text('a'), new Text('aaa'));
    $this->assertResult(true, 2, $c->matchPrefix(new ByteSource('aaa')));
    $c = Choice::longest(new Text('aa'), new Text('a'), new Text('aaa'));
    $this->assertResult(true, 3, $c->matchPrefix(new ByteSource('aaa')));
    $c = Choice::shortest(new Text('aa'), new Text('a'), new Text('aaa'));
    $this->assertResult(true, 1, $c->matchPrefix(new ByteSource('aaa')));
});

test('set preference', function () {
    $c = (new Choice(new Text('aa'), new Text('a'), new Text('aaa')))->setPreference(ChoicePolicy::FIRST);
    $this->assertResult(true, 2, $c->matchPrefix(new ByteSource('aaa')));
    $c = (new Choice(new Text('aa'), new Text('a'), new Text('aaa')))->setPreference(ChoicePolicy::LONGEST);
    $this->assertResult(true, 3, $c->matchPrefix(new ByteSource('aaa')));
    $c = (new Choice(new Text('aa'), new Text('a'), new Text('aaa')))->setPreference(ChoicePolicy::SHORTEST);
    $this->assertResult(true, 1, $c->matchPrefix(new ByteSource('aaa')));
});

test('add parser', function () {
    $choice = Choice::longest(new Text('a'), new Text('aa'));
    $this->assertResult(true, 2, $choice->matchPrefix(new ByteSource('aaa')));
    $choice->add(new Text('aaa'));
    $this->assertResult(true, 3, $choice->matchPrefix(new ByteSource('aaa')));
});

test('equal length alternatives retain first output and effects', function (ChoicePolicy $preference): void {
    $events = [];
    $first = (new Text('a'))->name('first')->setResult('first')->onCommit(
        function () use (&$events) {
            $events[] = 'first';
        },
    );
    $second = (new Text('a'))->name('second')->setResult('second')->onCommit(
        function () use (&$events) {
            $events[] = 'second';
        },
    );
    $result = (new Choice($first, $second))->setPreference($preference)->parseAll(new ByteSource('a'));

    $this->assertTrue($result->success);
    $this->assertSame('first', $result->node->children[0]->name);
    $this->assertSame(['first' => 'a'], $result->results());
    $this->assertSame([], $events);
    $this->assertTrue($result->commit());
    $this->assertTrue($result->commit());
    $this->assertSame(['first'], $events);
})->with(ChoiceTest::preferenceData());

test('success outranks longer failed attempt in either order', function (ChoicePolicy $preference): void {
    foreach ([false, true] as $failureFirst) {
        foreach ([new Text('a'), new Nothing()] as $success) {
            $failure = new Text('abc');
            $alternatives = $failureFirst ? [$failure, $success] : [$success, $failure];
            $result = (new Choice(...$alternatives))->setPreference($preference)->matchPrefix(new ByteSource('ab'));

            $this->assertTrue($result->success);
            $this->assertSame($success instanceof Nothing ? 0 : 1, $result->consumedByteLength);
            $this->assertSame($success::class, $result->node->children[0]->kind);
        }
    }
})->with(ChoiceTest::preferenceData());

test('failure progress is distinct from farthest diagnostic', function (ChoicePolicy $preference): void {
    foreach ([false, true] as $longerFirst) {
        $alternatives = [new Text('aaa'), new Text('abc')];
        if ($longerFirst) {
            $alternatives = array_reverse($alternatives);
        }
        $source = new ByteSource('ab');
        $match = (new Choice(...$alternatives))->setPreference($preference)->recognizeInSession(
            $source,
            0,
            new ParseSession($source),
        );

        $this->assertFalse($match->accepted);
        $this->assertSame($preference === ChoicePolicy::SHORTEST ? 1 : 2, $match->length);
        $this->assertSame(2, $match->diagnostic->byteOffset);
        $this->assertSame(0, $match->span->byteLength());
    }
})->with(ChoiceTest::preferenceData());
