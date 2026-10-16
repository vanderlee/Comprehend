<?php

declare(strict_types=1);

namespace Tests\Src\Match;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Runtime\ParseSession;
use WeakReference;

uses(TestCase::class);

test('preceding syntax without deferred operations is not retained by the execution graph', function (): void {
    $source = new ByteSource(' ');
    $child = (new Text(' '))->recognizeInSession($source, 0, new ParseSession($source));
    $this->assertInstanceOf(Success::class, $child);
    $reference = WeakReference::create($child);
    $parent = new Success();
    $parent->addPrecedingMatch($child);
    unset($child);

    $this->assertNull($reference->get());
});

test('preceding deferred effects are retained and run before local effects exactly once', function (): void {
    $calls = [];
    $source = new ByteSource(' ');
    $parser = (new Text(' '))->onCommit(static function () use (&$calls): void {
        $calls[] = 'trivia';
    });
    $child = $parser->recognizeInSession($source, 0, new ParseSession($source));
    $this->assertInstanceOf(Success::class, $child);
    $reference = WeakReference::create($child);
    $parent = new Success();
    $parent->setSourceMetadata($source->span(0, 0), null);
    $parent->addPrecedingMatch($child);
    $parent->addCustomCallback(static function () use (&$calls): void {
        $calls[] = 'parent';
    });
    unset($child);

    $this->assertNotNull($reference->get());
    $this->assertSame([], $calls);
    $parent->commit();
    $parent->commit();
    $this->assertSame(['trivia', 'parent'], $calls);
});

test('concrete nodes require source metadata', function (): void {
    $success = new Success(1);
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('Concrete nodes require source-bound recognition');

    $success->buildConcreteNode(new Text('a'));
});

test('value mapping is lazy and cached independently of commit', function (): void {
    $success = new Success(1);
    $success->setSourceMetadata((new ByteSource('a'))->span(0, 1), null);
    $success->buildConcreteNode(new Text('a'));
    $calls = 0;
    $success->setValueMapper(static function (ParseResult $result) use (&$calls): string {
        ++$calls;

        return strtoupper($result->rawText());
    });

    $this->assertTrue($success->accepted);
    $this->assertSame(0, $calls);
    $this->assertSame('A', $success->getValue());
    $this->assertSame('A', $success->getValue());
    $this->assertSame(1, $calls);
    $this->assertFalse($success->isCommitted());
});

test('commit runs effects only once', function (): void {
    $success = new Success();
    $success->setSourceMetadata((new ByteSource(''))->span(0, 0), null);
    $calls = 0;
    $success->addCustomCallback(static function () use (&$calls): void {
        ++$calls;
    });

    $success->commit();
    $success->commit();

    $this->assertSame(1, $calls);
    $this->assertTrue($success->isCommitted());
});

test('parent collections apply child operations even when child results are cached', function (): void {
    $first = new Success();
    $first->addResultCallback(static function (array &$results): void {
        $results['text'] = 'first';
    });
    $second = new Success();
    $second->addResultCallback(static function (array &$results): void {
        $results['text'] = ($results['text'] ?? '') . ' second';
    });
    $parent = new Success(0, [$first, $second]);

    $this->assertSame(['text' => ' second'], $second->getResults());
    $this->assertSame(['text' => 'first second'], $parent->getResults());
    $this->assertSame(['text' => ' second'], $second->getResults());
    $this->assertSame(['text' => 'first second'], $parent->getResults());
});
