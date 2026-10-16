<?php

declare(strict_types=1);

namespace Tests\Unit\src\Syntax;

use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Syntax;
use Vanderlee\Comprehend\Syntax\SyntaxExtension;

uses(TestCase::class);

test('extension generators install in order without invoking factories', function (): void {
    $events = [];
    $extension = new class ($events) implements SyntaxExtension {
        public function __construct(private array &$events)
        {
            // Retain the caller's log to distinguish installation from factory invocation.
        }

        public function factories(): iterable
        {
            $this->events[] = 'install';
            yield 'word' => function (string $text = 'hello'): Text {
                $this->events[] = 'factory';
                return new Text($text);
            };
            $this->events[] = 'installed';
        }
    };
    $base = Syntax::create();
    $syntax = $base->extend($extension);
    $this->assertSame(['install', 'installed'], $events);
    $this->assertNotContains('word', $base->names());
    $parser = $syntax->word(text: 'world');
    $this->assertSame(['install', 'installed', 'factory'], $events);
    $this->assertTrue($parser->freeze()->parse('world')->success);
    $this->assertSame(['install', 'installed', 'factory'], $events);
});

test('array extensions and yielded duplicate names follow define replacement semantics', function (): void {
    $base = Syntax::create()->define('word', static fn(): C => C::text('old'));
    $old = $base->word();
    $syntax = $base->extend(new class implements SyntaxExtension {
        public function factories(): iterable
        {
            yield 'WORD' => static fn(): C => C::text('first');
            yield 'word' => static fn(): C => C::text('last');
        }
    })->extend(new class implements SyntaxExtension {
        public function factories(): iterable
        {
            return ['suffix' => static fn(): C => C::text('!')];
        }
    });
    $this->assertTrue($old->parse('old')->success);
    $this->assertTrue($base->word()->parse('old')->success);
    $this->assertTrue($syntax->word()->suffix()->parse('last!')->success);
    $this->assertNotContains('suffix', $base->names());
});

test('extension installation errors never publish partial registrations', function (bool $invalidName): void {
    $base = Syntax::create();
    $extension = new class ($invalidName) implements SyntaxExtension {
        public function __construct(private bool $invalidName)
        {
            // Exercise registration failure as well as generator failure.
        }

        public function factories(): iterable
        {
            yield 'partial' => static fn(): C => C::text('a');
            if ($this->invalidName) {
                yield 'text' => static fn(): C => C::text('b');
            }
            throw new RuntimeException('Installation failed');
        }
    };
    try {
        $base->extend($extension);
        $this->fail('Expected installation failure');
    } catch (Exception $error) {
        $this->assertInstanceOf($invalidName ? InvalidArgumentException::class : RuntimeException::class, $error);
    }
    $this->assertNotContains('partial', $base->names());
})->with([false, true]);
