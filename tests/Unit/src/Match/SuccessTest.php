<?php

declare(strict_types=1);

namespace Tests\Src\Match;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Result\ValidationResult;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Runtime\ParseSession;
use WeakReference;

uses(TestCase::class);

test('a plain child keeps its concrete node when the wrapper gains semantic state', function (): void {
    $source = new ByteSource('x');
    $child = (new Text('x'))->recognizeInSession($source, 0, new ParseSession($source));
    $this->assertInstanceOf(Success::class, $child);
    $parent = new Success(1, [$child]);
    $parent->setSourceMetadata($source->span(0, 1), null);
    $parent->setValueMapper(static fn(): int => 7);
    $parent->buildConcreteNode(new Text('x'));
    $parent->retainExecutionState();

    $this->assertSame($child->node, $parent->node->children[0]);
    $this->assertSame(7, $parent->getValue());
    $this->assertTrue($parent->validateSemantic()->valid);
});

test('explicit concrete children replace the plain constructor child', function (): void {
    $source = new ByteSource('x');
    $parser = new Text('x');
    $first = $parser->recognizeInSession($source, 0, new ParseSession($source));
    $second = $parser->recognizeInSession($source, 0, new ParseSession($source));
    $parent = new Success(1, [$first]);
    $parent->setSourceMetadata($source->span(0, 1), null);
    $parent->setConcreteMatches([$second]);
    $parent->buildConcreteNode($parser);
    $parent->retainExecutionState();

    $this->assertSame([$second->node], $parent->node->children);
});

test('concrete-only value projections are released after tree construction', function (): void {
    $source = new ByteSource('x');
    $child = new Success(1);
    $child->setSourceMetadata($source->span(0, 1), null);
    $child->setValueMapper(static fn(ParseResult $result): string => strtoupper($result->rawText()));
    $child->buildConcreteNode(new Text('x'));
    $child->retainExecutionState();
    $reference = WeakReference::create($child);
    $parent = new Success(1);
    $parent->setSourceMetadata($source->span(0, 1), null);
    $parent->setConcreteMatches([$child]);
    $parent->buildConcreteNode(new Text('x'));
    $parent->retainExecutionState();
    unset($child);
    $this->assertNull($reference->get());
    $this->assertSame('x', $parent->node->children[0]->rawText());
    $this->assertTrue($parent->validateSemantic()->valid);
});

test('selected value-only children retain observable commit state', function (): void {
    $source = new ByteSource('x');
    $child = new Success(1);
    $child->setSourceMetadata($source->span(0, 1), null);
    $child->setValueMapper(static fn(ParseResult $result): ParseResult => $result);
    $child->buildConcreteNode(new Text('x'));
    $child->retainExecutionState();
    $parent = new Success(1, [$child]);
    $parent->setSourceMetadata($source->span(0, 1), null);
    $parent->buildConcreteNode(new Text('x'));
    $parent->retainExecutionState();
    $exposed = $child->getValue();
    $this->assertFalse($exposed->isCommitted());
    $parent->commit();
    $this->assertTrue($exposed->isCommitted());
    $this->assertSame($exposed, $child->getValue());
});

test('custom execution records retain their validation behavior', function (): void {
    $source = new ByteSource('x');
    $child = new class (1) extends Success {
        public function validateSemantic(): ValidationResult
        {
            return new ValidationResult([new \Vanderlee\Comprehend\Result\ValidationIssue(
                'custom.validation',
                'custom validation',
                $this->span ?? throw new \LogicException('Missing source span'),
            )]);
        }
    };
    $child->setSourceMetadata($source->span(0, 1), null);
    $child->setValueMapper(static fn(): int => 1);
    $child->buildConcreteNode(new Text('x'));
    $child->retainExecutionState();
    $parent = new Success(1, [$child]);
    $parent->setSourceMetadata($source->span(0, 1), null);
    $parent->buildConcreteNode(new Text('x'));
    $parent->retainExecutionState();
    $this->assertSame(['custom validation'], array_column($parent->validateSemantic()->issues, 'message'));
});

test('shared descendants contribute cached issues once through multiple parents', function (): void {
    $source = new ByteSource('x');
    $child = (new Text('x'))->validateSemantic(static fn(): string => 'shared')
        ->recognizeInSession($source, 0, new ParseSession($source));
    $left = new Success(1, [$child]);
    $right = new Success(1, [$child]);
    $parent = new Success(1, [$left, $right]);
    foreach ([$left, $right, $parent] as $match) {
        $match->setSourceMetadata($source->span(0, 1), null);
    }
    $report = $parent->validateSemantic();
    $this->assertCount(1, $report->issues);
    $this->assertSame($child->validateSemantic()->issues[0], $report->issues[0]);
});

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
