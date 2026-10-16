<?php

declare(strict_types=1);

namespace Tests\Unit\src\Syntax;

use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Extraction;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Syntax;
use Vanderlee\Comprehend\Syntax\SyntaxExtension;

uses(TestCase::class);

test('empty static and fluent registrations retain the original vocabulary', function (): void {
    $base = Syntax::create();
    $extension = new class implements SyntaxExtension {
        public function rules(): iterable
        {
            return [];
        }

        public function fluents(): iterable
        {
            return [];
        }
    };
    $this->assertSame($base, $base->extend($extension));
});

test('extension generators install in order without invoking factories', function (): void {
    $events = [];
    $extension = new class ($events) implements SyntaxExtension {
        public function __construct(private array &$events)
        {
            // Retain the caller's log to distinguish installation from factory invocation.
        }

        public function fluents(): iterable
        {
            return [];
        }

        public function rules(): iterable
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
    $base = Syntax::create()->defineRule('word', static fn(): C => C::text('old'));
    $old = $base->word();
    $syntax = $base->extend(new class implements SyntaxExtension {
        public function fluents(): iterable
        {
            return [];
        }

        public function rules(): iterable
        {
            yield 'WORD' => static fn(): C => C::text('first');
            yield 'word' => static fn(): C => C::text('last');
        }
    })->extend(new class implements SyntaxExtension {
        public function fluents(): iterable
        {
            return [];
        }

        public function rules(): iterable
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

        public function fluents(): iterable
        {
            return [];
        }

        public function rules(): iterable
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

test('extensions register independent static and fluent factories without executing callbacks', function (): void {
    $events = [];
    $extension = new class ($events) implements SyntaxExtension {
        public function __construct(private array &$events)
        {
            // Observe installation separately from authoring callbacks.
        }

        public function rules(): iterable
        {
            $this->events[] = 'static';
            yield 'token' => static fn(string $text): C => C::text($text);
        }

        public function fluents(): iterable
        {
            $this->events[] = 'fluent';
            yield 'TOKEN' => static fn(C $item): C => $item->then('old');
            yield 'token' => function (C $item, string $suffix = '!'): C {
                $this->events[] = 'callback';
                return $item->thenIgnore($suffix);
            };
        }
    };
    $base = Extraction::syntax();
    $old = $base->text('a');
    $syntax = $base->extend($extension);
    $this->assertSame(['static', 'fluent'], $events);
    $item = $syntax->token('a');
    $wrapped = $item->token(suffix: '?')->token();
    $this->assertSame(['static', 'fluent', 'callback', 'callback'], $events);
    $result = $wrapped->parse('a?!');
    $this->assertTrue($result->success);
    $this->assertSame('a', $result->value());
    $this->assertSame('a?!', $result->rawText());
    $this->assertSame(['static', 'fluent', 'callback', 'callback'], $events);
    $this->assertTrue($item->parse('a')->success);
    $this->assertTrue($old->parse('a')->success);
    $this->assertNotContains('token', $base->names());
    $changed = $syntax->defineFluent('token', static fn(C $item): C => $item->then('.'));
    $this->assertTrue($changed->token('a')->token()->parse('a.')->success);
    $this->assertTrue($item->token()->parse('a!')->success);
});

test('fluent installation failure leaves both registration sets unpublished', function (bool $invalidName): void {
    $base = C::syntax();
    $extension = new class ($invalidName) implements SyntaxExtension {
        public function __construct(private bool $invalidName)
        {
            // Exercise both registration and iterable failures.
        }

        public function rules(): iterable
        {
            return ['partialStatic' => static fn(): C => C::text('a')];
        }

        public function fluents(): iterable
        {
            yield 'partialFluent' => static fn(C $item): C => $item->many();
            if ($this->invalidName) {
                yield 'text' => static fn(C $item): C => $item;
            }
            throw new RuntimeException('Fluent installation failed');
        }
    };
    try {
        $base->extend($extension);
        $this->fail('Expected fluent installation failure');
    } catch (Exception $error) {
        $this->assertInstanceOf($invalidName ? InvalidArgumentException::class : RuntimeException::class, $error);
    }
    $this->assertNotContains('partialstatic', $base->names());
    $this->assertNotContains('partialfluent', $base->names());
})->with([false, true]);
