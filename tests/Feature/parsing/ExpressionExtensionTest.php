<?php

declare(strict_types=1);

namespace Tests\Feature\parsing;

use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Extensions\Extraction\Extraction;
use Vanderlee\Comprehend\Extensions\Expression\Operator as O;
use Vanderlee\Comprehend\Extensions\Expressions\Expressions;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ChoicePolicy;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\Utf8FileSource;

uses(TestCase::class);

test('grouped expressions preserve recursive values and parentheses', function (): void {
    $syntax = Extraction::syntax()->extend(new Expressions());
    $rules = new Ruleset();
    $number = Extraction::syntax()->regex('/[0-9]+/A')->named('number')->mapText('intval');
    $primary = $syntax->expressionChoice($number, $syntax->of($rules->rule('expr'))->between('(', ')'));
    $rules->define('expr', $syntax->operatorExpression(
        $primary,
        O::infixLeft('+', 10, static fn(int $a, int $b): int => $a + $b),
        O::infixLeft('*', 20, static fn(int $a, int $b): int => $a * $b),
        O::prefix('-', 30, static fn(int $a): int => -$a),
        O::infixRight('^', 40, static fn(int $a, int $b): int => $a ** $b),
    )->spacing(Extraction::syntax()->text(' ')->many()))->root('expr');
    $grammar = $rules->freeze();
    foreach (['(2+3)*4' => 20, '2*(3+4)' => 14, '((2))' => 2, '-(2+3)^2' => -25] as $input => $value) {
        $result = $grammar->parse($input);
        $this->assertTrue($result->success, $input);
        $this->assertSame($value, $result->value());
        $this->assertSame($input, $result->rawText());
    }
    $this->assertFalse($grammar->parse('(2+3')->success);
    $this->assertFalse($grammar->parse('()')->success);
});

test('reductions can build typed domain trees instead of evaluating numbers', function (): void {
    $syntax = Extraction::syntax()->extend(new Expressions());
    $primary = Extraction::syntax()->regex('/[a-z]/A')->mapText(static fn(string $name): array => ['variable', $name]);
    $parser = $syntax->operatorExpression(
        $primary,
        O::infixLeft('+', 10, static fn(array $a, array $b): array => ['add', $a, $b]),
        O::infixLeft('*', 20, static fn(array $a, array $b): array => ['multiply', $a, $b]),
    );
    $this->assertSame(
        ['add', ['variable', 'a'], ['multiply', ['variable', 'b'], ['variable', 'c']]],
        $parser->parse('a+b*c')->value(),
    );
});

test('expression reduction is lazy cached and replaceable by an explicit outer mapping', function (): void {
    $syntax = Extraction::syntax()->extend(new Expressions());
    $calls = [];
    $parser = $syntax->operatorExpression(
        Extraction::syntax()->regex('/[0-9]+/A')->mapText('intval'),
        O::infixLeft('+', 1, static function (int $a, int $b) use (&$calls): int {
            $calls[] = [$a, $b];
            return $a + $b;
        }),
    );
    $result = $parser->parse('1+2+3');
    $result->ast();
    $this->assertSame([], $calls);
    $this->assertSame(6, $result->value());
    $this->assertSame(6, $result->value());
    $this->assertSame([[1, 2], [3, 3]], $calls);
    $this->assertSame(5, $parser->mapText('strlen')->parse('1+2+3')->value());
    $this->assertSame([[1, 2], [3, 3]], $calls);
    $this->assertSame(3, $parser->parse('1+2')->value());
    $this->assertCount(3, $calls);
    $this->assertSame($parser->freeze(), $parser->freeze());
});

test('right reduction order and retained exceptions are local to a recognition', function (): void {
    $syntax = Extraction::syntax()->extend(new Expressions());
    $calls = [];
    $parser = $syntax->operatorExpression(
        Extraction::syntax()->regex('/[0-9]/A')->mapText('intval'),
        O::infixRight('^', 1, static function (int $a, int $b) use (&$calls): int {
            $calls[] = [$a, $b];
            return $a ** $b;
        }),
    );
    $this->assertSame(512, $parser->parse('2^3^2')->value());
    $this->assertSame([[3, 2], [2, 9]], $calls);
    $error = new RuntimeException('Reduction failed');
    $failures = 0;
    $bad = $syntax->operatorExpression('a', O::infixLeft('+', 1, static function () use ($error, &$failures): never {
        ++$failures;
        throw $error;
    }));
    $result = $bad->parse('a+a');
    for ($read = 0; $read < 2; ++$read) {
        try {
            $result->value();
            $this->fail('Expected reduction failure');
        } catch (RuntimeException $caught) {
            $this->assertSame($error, $caught);
        }
    }
    $this->assertSame(1, $failures);
});

test('operator syntax values stay lazy while all selected syntax retains validation and effects', function (): void {
    $syntax = Extraction::syntax()->extend(new Expressions());
    $events = [];
    $effect = static function (ParseResult $result) use (&$events): void {
        $events[] = $result->rawText();
    };
    $operator = Extraction::syntax()->text('+')->named('plus')->pushResult('operators')->onCommit($effect)
        ->mapText(static function (): never {
            throw new RuntimeException('Operator value should not be needed for reduction');
        });
    $operand = Extraction::syntax()->text('a')->named('operand')->pushResult('operands')->onCommit($effect);
    $parser = $syntax->operatorExpression(
        $operand,
        O::infixLeft($operator, 10, static fn(string $a, string $b): string => $a . $b),
    )->spacing(Extraction::syntax()->text(' ')->onCommit($effect));
    $result = $parser->parse('a + a');
    $this->assertSame('aa', $result->value());
    $this->assertSame(['operands' => ['a', 'a'], 'operators' => ['+']], $result->results());
    $this->assertSame([], $events);
    $this->assertTrue($result->commit());
    $this->assertTrue($result->commit());
    $this->assertSame(['a', ' ', '+', ' ', 'a'], $events);
    $invalid = $syntax->operatorExpression('a', O::infixLeft(
        Extraction::syntax()->text('+')->validateSemantic(static fn(): string => 'bad operator'),
        10,
        static fn($a, $b) => $a,
    ))->parse('a+a');
    $this->assertSame(['bad operator'], array_column($invalid->validate()->issues, 'message'));
    $this->assertFalse($invalid->commit());
});

test('abandoned tails and branches contribute no reductions validation or effects', function (): void {
    $syntax = Extraction::syntax()->extend(new Expressions());
    $calls = 0;
    $op = Extraction::syntax()->text('+')->validateSemantic(static fn(): string => 'discarded')->onCommit(
        static function () use (&$calls): void {
            ++$calls;
        },
    );
    $parser = $syntax->operatorExpression('a', O::infixLeft($op, 1, static function () use (&$calls): string {
        ++$calls;
        return 'reduced';
    }))->spacing(Extraction::syntax()->text(' ')->many());
    $prefix = $parser->matchPrefix('a + x');
    $this->assertSame('a', $prefix->rawText());
    $this->assertSame('a', $prefix->value());
    $this->assertTrue($prefix->commit());
    $this->assertSame(0, $calls);
    $result = $parser->then('!')->or('a+a?')->parse('a+a?');
    $this->assertTrue($result->commit());
    $this->assertSame(0, $calls);
});

test('expression syntax retains utf8 byte spans across file and byte sources', function (): void {
    $syntax = Extraction::syntax()->extend(new Expressions());
    $parser = $syntax->operatorExpression(
        Extraction::syntax()->text('é')->named('operand'),
        O::infixLeft(Extraction::syntax()->text('⊕')->named('operator'), 10, static fn($a, $b): string => $a . $b),
    );
    $stream = tmpfile();
    fwrite($stream, 'é⊕é');
    $source = new Utf8FileSource($stream, 4);
    fclose($stream);
    $result = $parser->parse($source);
    $this->assertSame('éé', $result->value());
    $this->assertSame('é⊕é', $result->rawText());
    $this->assertSame([0, 7], [$result->start, $result->end]);
    $this->assertSame(2, $result->ast()->firstNamed('operator')->span->start);
    $this->assertSame(5, $result->ast()->allNamed('operand')[1]->span->start);
    $this->assertSame('éé', $parser->parse(new ByteSource('é⊕é'))->value());
    $this->assertSame(9, $parser->matchPrefix('xxé⊕é!', byteOffset: 2)->end);
});

test('expression extractions honor declaration order and scoped longest choice', function (): void {
    $syntax = Extraction::syntax()->extend(new Expressions());
    $parser = $syntax->operatorExpression(
        'a',
        O::infixLeft('*', 1, static fn($a, $b): string => 'short'),
        O::infixLeft('**', 1, static fn($a, $b): string => 'long'),
    );
    $this->assertFalse($parser->parse('a**a')->success);
    $this->assertSame('long', $parser->prefer(ChoicePolicy::LONGEST)->parse('a**a')->value());
});

test('expression graph isolation retains snapshots and respects local spacing overrides', function (): void {
    $syntax = Extraction::syntax()->extend(new Expressions());
    $operator = new Sequence(new Text('+'));
    $primary = new Sequence(new Text('a'));
    $parser = $syntax->operatorExpression($primary, O::infixLeft($operator, 1, static fn($a, $b) => $a . $b));
    $frozen = $parser->freeze();
    $operator->add(new Text('+'));
    $primary->add(new Text('b'));
    $this->assertSame('aa', $frozen->parse('a+a')->value());
    $this->assertSame('aa', $parser->parse('a+a')->value());
    $this->assertSame('aa', $parser->parse('a + a', spacing: Extraction::syntax()->text(' '))->value());
    $this->assertFalse($parser->lexeme()->parse('a + a', spacing: Extraction::syntax()->text(' '))->success);
    $this->assertFalse($parser->spacing(null)->parse('a + a', spacing: Extraction::syntax()->text(' '))->success);
});

test('expression parsing uses shared limits and excludes nullable custom operators at runtime', function (): void {
    $syntax = Extraction::syntax()->extend(new Expressions());
    $opaque = new class ('unused') extends Text {
        protected function recognize(Source $source, int $offset, ParseSession $session): AbstractMatch
        {
            return $this->success($source, $offset);
        }
    };
    $parser = $syntax->operatorExpression('a', O::infixLeft($opaque, 1, static fn($a, $b) => $a));
    try {
        $parser->parser()->parseAll(new ByteSource('aa'));
        $this->fail('Expected progress error');
    } catch (LogicException $error) {
        $this->assertStringContainsString('without consuming', $error->getMessage());
    }
    $this->expectException(ParseLimitException::class);
    $syntax->operatorExpression('a', O::infixLeft('+', 1, static fn($a, $b) => $a))
        ->parse('a+a', limits: new ParseLimits(maxWork: 2));
});
