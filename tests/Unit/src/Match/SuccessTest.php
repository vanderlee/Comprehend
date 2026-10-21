<?php

declare(strict_types=1);

namespace Tests\Src\Match;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Result\ValidationResult;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Runtime\ParseSession;
use WeakReference;
use Vanderlee\Comprehend\Tree\ConcreteNode;

uses(TestCase::class);

test('mixed direct syntax retains lazy values validation and explicit child effects', function (): void {
    $events = [];
    $source = new ByteSource('a,a');
    $item = (new Text('a'))->mapValue(static fn(): int => 7)
        ->validateSemantic(static function () use (&$events): bool {
            $events[] = 'validate';

            return true;
        })->onCommit(static function () use (&$events): void {
            $events[] = 'commit';
        });
    $child = $item->recognizeInSession($source, 0, new ParseSession($source));
    $punctuation = new ConcreteNode(Text::class, $source->span(1, 2));
    $parent = new Success(2, [$child]);
    $parent->setSourceMetadata($source->span(0, 2), null);
    $parent->setConcreteChildren([$child, $punctuation]);
    $parent->setValueMapper(static fn(): mixed => $child->getValue());
    $parent->preparePlainNode(new Sequence(new Text('a'), new Text(',')));
    $result = new ParseResult($parent);
    $this->assertSame([$child->node, $punctuation], $result->node->children);
    $this->assertSame([], $events);
    $this->assertSame(7, $result->value());
    $this->assertSame(7, $result->value());
    $this->assertTrue($result->commit());
    $this->assertTrue($result->commit());
    $this->assertSame(['validate', 'commit'], $events);
});

test('only built in stateless execution records can be replaced by syntax alone', function (): void {
    $plain = new Success();
    $this->assertFalse($plain->hasDeferredState());
    $plain->setValueMapper(static fn(): int => 7);
    $this->assertTrue($plain->hasDeferredState());
    $custom = new class extends Success {
        public function getResults(): array
        {
            return ['custom' => 7];
        }
    };
    $this->assertTrue($custom->hasDeferredState());
});

test('mixed sparse children keep CST order and deferred operations after retention', function (): void {
    $source = new ByteSource('abc');
    $calls = [];
    $children = [];
    foreach (['a', 'b', 'c'] as $offset => $text) {
        $parser = new Text($text);
        if ($text !== 'b') {
            $parser->onCommit(static function () use (&$calls, $text): void {
                $calls[] = $text;
            });
        }
        $children[$offset * 2 + 1] = $parser->recognizeInSession($source, $offset, new ParseSession($source));
    }
    $plain = WeakReference::create($children[3]);
    $nodes = array_map(static fn(Success $child) => $child->node, array_values($children));
    $parent = new Success(3, $children);
    $parent->setSourceMetadata($source->span(0, 3), null);
    $parent->buildConcreteNode(new Text('abc'));
    $parent->retainExecutionState();
    unset($children);

    $this->assertNull($plain->get());
    $this->assertSame($nodes, $parent->node->children);
    $this->assertSame('abc', $parent->node->rawText());
    $this->assertTrue($parent->validateSemantic()->valid);
    $this->assertSame([], $calls);
    $parent->commit();
    $parent->commit();
    $this->assertSame(['a', 'c'], $calls);
});

test('plain siblings keep their trees and release execution records when the parent gains a value', function (): void {
    $source = new ByteSource('xy');
    $left = (new Text('x'))->recognizeInSession($source, 0, new ParseSession($source));
    $right = (new Text('y'))->recognizeInSession($source, 1, new ParseSession($source));
    $references = [WeakReference::create($left), WeakReference::create($right)];
    $nodes = [$left->node, $right->node];
    $parent = new Success(2, [$left, $right]);
    $state = new \ReflectionProperty(Success::class, 'state');
    $this->assertNull($state->getValue($parent));
    $parent->setConcreteMatches([$left, $right]);
    $this->assertNull($state->getValue($parent));
    $parent->setSourceMetadata($source->span(0, 2), null);
    $parent->setValueMapper(static fn(): int => 7);
    $parent->buildConcreteNode(new Text('xy'));
    $parent->retainExecutionState();
    unset($left, $right);

    $this->assertSame($nodes, $parent->node->children);
    $this->assertSame('xy', $parent->node->rawText());
    $this->assertNull($references[0]->get());
    $this->assertNull($references[1]->get());
    $this->assertSame(7, $parent->getValue());
    $this->assertTrue($parent->validateSemantic()->valid);
});

test('replacing plain siblings retains concrete child validation and deferred effects', function (): void {
    $source = new ByteSource('xy');
    $calls = 0;
    $left = (new Text('x'))->recognizeInSession($source, 0, new ParseSession($source));
    $right = (new Text('y'))->recognizeInSession($source, 1, new ParseSession($source));
    $parent = new Success(2, [$left, $right]);
    $replacement = (new Text('xy'))->validateSemantic(static fn(): string => 'invalid replacement')
        ->onCommit(static function () use (&$calls): void {
            ++$calls;
        })->recognizeInSession($source, 0, new ParseSession($source));
    $parent->setSourceMetadata($source->span(0, 2), null);
    $parent->setConcreteMatches([$replacement]);
    $parent->buildConcreteNode(new Text('xy'));
    $parent->retainExecutionState();

    $this->assertSame([$replacement->node], $parent->node->children);
    $this->assertSame(['invalid replacement'], array_column($parent->validateSemantic()->issues, 'message'));
    $this->assertSame(0, $calls);
});

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

test('direct concrete children survive node preparation alongside lazy values and explicit effects', function (): void {
    $source = new ByteSource('ab');
    $nodes = [
        new ConcreteNode(Text::class, $source->span(0, 1)),
        new ConcreteNode(Text::class, $source->span(1, 2)),
    ];
    $parent = new Success(2);
    $parent->setSourceMetadata($source->span(0, 2), null);
    $parent->setConcreteNodes($nodes);
    $parent->setValueMapper(static fn(ParseResult $result): string => strtoupper($result->rawText()));
    $effects = 0;
    $parent->addCustomCallback(static function () use (&$effects): void {
        ++$effects;
    });
    $parent->buildConcreteNode(new Text('ab'), 'word');
    $parent->retainExecutionState();

    $this->assertSame($nodes, $parent->node->children);
    $this->assertSame('ab', $parent->node->rawText());
    $this->assertSame('AB', $parent->getValue());
    $this->assertSame('AB', $parent->getValue());
    $this->assertSame(0, $effects);
    $parent->commit();
    $parent->commit();
    $this->assertSame(1, $effects);
});

test('direct and execution-backed concrete children replace one another including empty lists', function (): void {
    $source = new ByteSource('a');
    $child = (new Text('a'))->recognizeInSession($source, 0, new ParseSession($source));
    $this->assertInstanceOf(Success::class, $child);
    $replacement = new ConcreteNode(Text::class, $source->span(0, 1), name: 'replacement');
    $parent = new Success(1, [$child]);
    $parent->setSourceMetadata($source->span(0, 1), null);
    $parent->setConcreteNodes([$replacement]);
    $parent->preparePlainNode(new Text('a'));
    $this->assertSame([$replacement], $parent->node->children);
    $parent->setConcreteNodes([$replacement]);
    $parent->setConcreteMatches([$child]);
    $parent->preparePlainNode(new Text('a'));
    $this->assertSame([$child->node], $parent->node->children);
    $parent->setConcreteMatches([$child]);
    $parent->setConcreteNodes([]);
    $parent->preparePlainNode(new Text('a'));
    $this->assertSame([], $parent->node->children);
});

test('direct concrete children still reject nodes from another source', function (): void {
    $parent = new Success(1);
    $parent->setSourceMetadata((new ByteSource('a'))->span(0, 1), null);
    $parent->setConcreteNodes([new ConcreteNode(Text::class, (new ByteSource('a'))->span(0, 1))]);
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Concrete children must be nodes from the same source');
    $parent->preparePlainNode(new Text('a'));
});
