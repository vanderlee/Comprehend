<?php

declare(strict_types=1);

namespace Tests\Feature\semantics;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\Utf8Source;
use WeakReference;

uses(TestCase::class);

test('plain execution children are released while their named syntax remains available', function () {
    $unit = new class ('a') extends Text {
        public array $records = [];

        protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
        {
            $match = parent::recognize($source, $byteOffset, $session);
            $this->records[] = WeakReference::create($match);

            return $match;
        }
    };
    $unit->name('letter');
    $result = Repeat::exact($unit, 3)->parseAll(new Utf8Source('aaa'));
    $this->assertTrue($result->success);
    foreach ($unit->records as $record) {
        $this->assertNull($record->get());
    }
    $this->assertCount(3, $result->node->children);
    $this->assertCount(3, $result->ast()->children);
    $this->assertSame('aaa', $result->value());
    $this->assertSame('aaa', $result->rawText());
    $this->assertTrue($result->commit());
    $this->assertTrue($result->isCommitted());
});

test('sparse selected descendants retain cached collections validation and effect order', function () {
    $events = [];
    $maps = 0;
    $unit = (new Text('a'))->pushResult('letters', function ($text) use (&$maps) {
        ++$maps;

        return strtoupper($text);
    })->validateSemantic(static fn() => true)->onCommit(function () use (&$events) {
        $events[] = 'letter';
    });
    $parser = Repeat::exact($unit, 3);
    $first = $parser->parseAll(new Utf8Source('aaa'));
    $second = $parser->parseAll(new Utf8Source('aaa'));
    $this->assertSame(0, $maps);
    $this->assertSame(['A', 'A', 'A'], $first->result('letters'));
    $this->assertSame(['A', 'A', 'A'], $first->result('letters'));
    $this->assertSame(3, $maps);
    $this->assertTrue($first->commit());
    $this->assertTrue($first->commit());
    $this->assertSame(['letter', 'letter', 'letter'], $events);
    $this->assertFalse($second->isCommitted());
    $this->assertSame(['A', 'A', 'A'], $second->result('letters'));
    $this->assertSame(6, $maps);
});
