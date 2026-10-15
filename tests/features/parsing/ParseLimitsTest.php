<?php

declare(strict_types=1);

namespace Tests\features\parsing;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Parser\Stub;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Source\Utf8Source;

uses(TestCase::class);

test('long literal stops at work limit before committing effects', function () {
    $calls = 0;
    $parser = (new Text(str_repeat('a', 100)))->onCommit(function () use (&$calls) {
        ++$calls;
    });
    try {
        $parser->parseAll(new Utf8Source(str_repeat('a', 100)), limits: new ParseLimits(maxWork: 10));
        $this->fail('Expected budget exhaustion');
    } catch (ParseLimitException $error) {
        $this->assertSame('work', $error->limit);
        $this->assertSame(9, $error->byteOffset);
        $this->assertContains('Text', $error->rulePath);
    }
    $this->assertSame(0, $calls);
    $this->assertTrue($parser->parseAll(
        new Utf8Source(str_repeat('a', 100)),
        limits: new ParseLimits(maxWork: 101),
    )->success);
});

test('frozen consuming recursion stops at depth budget', function () {
    $stub = new Stub();
    $stub->setParser(new Choice(new Text('a'), new Sequence(new Text('('), $stub, new Text(')'))));
    $frozen = $stub->freeze();
    $text = str_repeat('(', 30) . 'a' . str_repeat(')', 30);
    try {
        $frozen->parseAll(new Utf8Source($text), limits: new ParseLimits(maxDepth: 12));
        $this->fail('Expected depth exhaustion');
    } catch (ParseLimitException $error) {
        $this->assertSame('depth', $error->limit);
        $this->assertGreaterThan(0, $error->byteOffset);
    }
    $this->assertTrue($frozen->parseAll(new Utf8Source('(a)'), limits: new ParseLimits(30, 100))->success);
});

test('speculative failures consume work rather than resetting budget', function () {
    $parser = new Choice(new Text('abcx'), new Text('abcy'), new Text('abcz'));
    $this->expectException(ParseLimitException::class);
    $parser->parseAll(new Utf8Source('abcz'), limits: new ParseLimits(maxWork: 10));
});

test('profiling capacity is bounded and output unchanged', function () {
    $source = new Utf8Source('ab');
    $shared = new Text('a');
    $parser = new Choice(new Sequence($shared, new Text('x')), new Sequence($shared, new Text('b')));
    $metrics = new \Vanderlee\Comprehend\Runtime\ParseMetrics(2);
    $session = new \Vanderlee\Comprehend\Runtime\ParseSession($source, metrics: $metrics);
    $match = $parser->recognizeInSession($source, 0, $session);
    $this->assertTrue($match->accepted);
    $this->assertSame(2, $metrics->snapshot()['tracked_positions']);
    $this->assertSame(7, $metrics->snapshot()['parser_entries']);
    $this->assertGreaterThan(0, $session->workUsed());
});
