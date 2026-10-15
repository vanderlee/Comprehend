<?php

declare(strict_types=1);

namespace Tests\features\authoring;

use LogicException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Abnf\Compiler;
use Vanderlee\Comprehend\Abnf\SyntaxException;
use Vanderlee\Comprehend\Runtime\ChoicePolicy;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\SourceMode;
use Vanderlee\Comprehend\Source\Utf8Source;

abstract class AbnfTest extends TestCase
{
    public static function supportedConstructs(): array
    {
        return [
            ['2DIGIT', '42', '4'],
            ['1*3DIGIT', '123', '1234'],
            ['*2%s"a"', '', 'aaa'],
            ['["a"] "b"', 'ab', 'a'],
            ['("a" / "b") "c"', 'bc', 'bd'],
            ['%s"Ab"', 'Ab', 'ab'],
            ['%i"Ab"', 'aB', 'ac'],
            ['%x41.42', 'AB', 'ab'],
            ['%d48-57', '7', 'x'],
            ['%b01000001', 'A', 'B'],
            ['%xFF', "\xff", "\xfe"],
            ['""', '', 'x'],
        ];
    }

    public static function malformedDefinitions(): array
    {
        return [
            ['root = missing'], ['root = <prose>'], ['root = "unfinished'],
            ['root = "a""b"'], ['root = ("a"'], ['root = %x'],
            ['root = %x100'], ['root = %d57-48'], ['root = 3*2DIGIT'],
            ["root = \"a\"\nROOT = \"b\""], ['root =/ "a"'],
            ['root = %q"a"'], ['root = é'], ["root = \"a\tb\""],
            ['root = 999999999999999999999999DIGIT'], ["root = \"a\"\r"],
        ];
    }
}

uses(AbnfTest::class);

test('forward case insensitive references and incremental rules', function (): void {
    $grammar = Compiler::compile(
        "Word = Prefix Suffix\nPrefix = %s\"a\"\nSuffix = \"b\"\nSUFFIX =/ \"c\"\n",
        'WORD',
    );

    $this->assertTrue($grammar->parseAll(new ByteSource('aB'))->success);
    $this->assertTrue($grammar->parseAll(new ByteSource('aC'))->success);
    $this->assertFalse($grammar->parseAll(new ByteSource('Ab'))->success);
    $result = $grammar->parseAll(new ByteSource('ab'));
    $this->assertSame('word', $result->ast()->children[0]->name);
    $this->assertSame('ab', $result->rawText());
});

test('supported constructs', function (string $definition, string $accepted, string $rejected): void {
    $grammar = Compiler::compile('root = ' . $definition, 'root');

    $this->assertTrue($grammar->parseAll(new ByteSource($accepted))->success);
    $this->assertFalse($grammar->parseAll(new ByteSource($rejected))->success);
})->with(AbnfTest::supportedConstructs());

test('comments and folded lf or cr lf lines', function (): void {
    foreach (["\n", "\r\n"] as $newline) {
        $text = '; comment' . $newline . 'root = "a" ; branch' . $newline
            . ' / "b"' . $newline . '  "c"' . $newline;
        $grammar = Compiler::compile($text, 'root');

        $this->assertTrue($grammar->parseAll(new ByteSource('a'))->success);
        $this->assertTrue($grammar->parseAll(new ByteSource('bc'))->success);
    }
});

test('numeric unicode encoding is explicit', function (): void {
    $grammar = Compiler::compile('root = %x1F642', 'root', SourceMode::UTF8);

    $this->assertTrue($grammar->parseAll(new Utf8Source('🙂'))->success);
    $this->assertSame('🙂', $grammar->parseAll(new Utf8Source('🙂'))->rawText());
    $this->expectException(SyntaxException::class);
    Compiler::compile('root = %x1F642', 'root');
});

test('local choice policy is explicit and does not retry suffixes', function (): void {
    $text = 'root = ("a" / "ab") "c"';
    $first = Compiler::compile($text, 'root');
    $longest = Compiler::compile($text, 'root', choice: ChoicePolicy::LONGEST);

    $this->assertFalse($first->parseAll(new ByteSource('abc'))->success);
    $this->assertTrue($longest->parseAll(new ByteSource('abc'))->success);
});

test('ascii quoted case folding does not admit unicode equivalents', function (): void {
    $grammar = Compiler::compile('root = "k"', 'root');

    $this->assertTrue($grammar->parseAll(new Utf8Source('K'))->success);
    $this->assertFalse($grammar->parseAll(new Utf8Source('K'))->success);
    $unicode = Compiler::compile('root = %x212A', 'root', SourceMode::UTF8);
    $this->assertTrue($unicode->parseAll(new Utf8Source('K'))->success);
});

test('malformed or unsupported definitions have locations', function (string $text): void {
    try {
        Compiler::compile($text, 'root');
        $this->fail('Expected grammar error');
    } catch (SyntaxException $error) {
        $this->assertStringContainsString('byte ', $error->getMessage());
        $this->assertGreaterThanOrEqual(0, $error->diagnostic->byteOffset);
        $this->assertLessThanOrEqual(strlen($text), $error->diagnostic->byteOffset);
    }
})->with(AbnfTest::malformedDefinitions());

test('generated graph keeps safety checks', function (): void {
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Nullable repetition');
    Compiler::compile('root = *""', 'root');
});

test('left recursion is still unsupported', function (): void {
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Left-recursive');
    Compiler::compile('root = root / "a"', 'root');
});
