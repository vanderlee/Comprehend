<?php

declare(strict_types=1);

namespace Tests\Feature\semantics;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Source;
use WeakReference;

uses(TestCase::class);

test('losing effect records are released while selected syntax and commit remain available', function () {
    $enabled = gc_enabled();
    gc_disable();
    try {
        $events = [];
        $short = new class ('a') extends Text {
            public ?WeakReference $record = null;

            protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
            {
                $match = parent::recognize($source, $byteOffset, $session);
                $this->record = WeakReference::create($match);

                return $match;
            }
        };
        $short->onCommit(static function () use (&$events): void {
            $events[] = 'loser';
        });
        $long = (new Text('aa'))->onCommit(static function () use (&$events): void {
            $events[] = 'selected';
        });
        $result = Choice::longest($short, $long)->parseAll(new ByteSource('aa'));
        $this->assertNull($short->record->get());
        $this->assertSame('aa', $result->node->rawText());
        $this->assertSame([], $events);
        $this->assertTrue($result->commit());
        $this->assertTrue($result->commit());
        $this->assertSame(['selected'], $events);
    } finally {
        if ($enabled) {
            gc_enable();
        }
    }
});

test('discarded effect and assignment records release their source without cycle collection', function () {
    $enabled = gc_enabled();
    gc_disable();
    try {
        $assigned = null;
        $calls = 0;
        $parser = (new Text('a'))->assignTo($assigned)->onCommit(static function () use (&$calls): void {
            ++$calls;
        });
        $source = new ByteSource('a');
        $match = $parser->recognizeInSession($source, 0, new ParseSession($source));
        $record = WeakReference::create($match);
        $sourceReference = WeakReference::create($source);
        unset($match, $source);

        $this->assertNull($record->get());
        $this->assertNull($sourceReference->get());
        $this->assertNull($assigned);
        $this->assertSame(0, $calls);
    } finally {
        if ($enabled) {
            gc_enable();
        }
    }
});

test('effect snapshots preserve assignments wrapper identity callback order and independent commits', function () {
    $assigned = null;
    $events = [];
    $wrappers = [];
    $parser = (new Text('a'))->assignTo($assigned)->onCommit(
        static function (ParseResult $result) use (&$events, &$wrappers, &$assigned): void {
            $events[] = $assigned;
            $wrappers[] = $result;
        },
    )->onCommit(static function (ParseResult $result) use (&$events, &$wrappers): void {
        $events[] = $result->rawText();
        $wrappers[] = $result;
    });
    $source = new ByteSource('a');
    $first = $parser->recognizeInSession($source, 0, new ParseSession($source));
    $second = $parser->recognizeInSession($source, 0, new ParseSession($source));
    $this->assertInstanceOf(Success::class, $first);
    $this->assertInstanceOf(Success::class, $second);
    $first->addCustomCallback('gc_enabled'); // Internal PHP functions reject extra arguments.
    $first->addCustomCallback(static function () use (&$events): void {
        $events[] = 'custom';
    });
    $parser->onCommit(static function () use (&$events): void {
        $events[] = 'late';
    });
    $first->commit();
    $first->commit();
    $this->assertSame(['a', 'a', 'custom'], $events);
    $this->assertSame($wrappers[0], $wrappers[1]);
    $this->assertFalse($second->isCommitted());
    $second->commit();
    $this->assertSame(['a', 'a', 'custom', 'a', 'a'], $events);
});
