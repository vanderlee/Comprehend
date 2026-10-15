<?php

declare(strict_types=1);

namespace Tests\features\authoring;

use LogicException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Directive\Prefer;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Source\Utf8Source;
use Vanderlee\Comprehend\Tree\ConcreteNode;

class ComprehendErgonomicsTest extends TestCase
{
    public function testFluentRulesKeepForwardRecursionNamesAndSnapshotIsolation(): void
    {
        $rules = new Ruleset([], 'syntax');
        $leaf = $rules->rule('leaf');
        $rules->root($rules->rule('nested'));
        $rules->define('nested', C::first(
            $leaf,
            C::text('(')->then($rules->rule('nested'), C::text(')')),
        ));
        $rules->define('leaf', C::text('x'));
        $grammar = $rules->freeze();
        $rules->define('leaf', C::text('y'));

        $result = $grammar->parseAll(new Utf8Source('((x))'));
        $this->assertTrue($result->success);
        $this->assertSame('nested', $result->ast()->children[0]->name);
        $this->assertSame('syntax', $result->ast()->children[0]->group);
        $this->assertSame('((x))', $result->ast()->rawText());
        $this->assertTrue($rules->freeze()->parseAll(new Utf8Source('((y))'))->success);
        $this->assertFalse($grammar->parseAll(new Utf8Source('((y))'))->success);
        $this->assertTrue($leaf->some()->freeze()->parseAll(new Utf8Source('yy'))->success);
        $this->assertFalse($leaf->freeze()->parseAll(new Utf8Source('yy'))->success);
        $this->assertInstanceOf(Parser::class, $rules->ref('leaf'));
    }

    public function testFluentParameterizedRulesResolveOnlyWhenFrozen(): void
    {
        $calls = 0;
        $rules = new Ruleset();
        $pair = $rules->rule('pair', 'x')->some();
        $rules->define('pair', static function (string $text) use (&$calls): Parser {
            ++$calls;
            return C::text($text)->then(C::text($text))->parser();
        });

        $this->assertSame(0, $calls);
        $grammar = $pair->freeze();
        $this->assertSame(1, $calls);
        $this->assertTrue($grammar->parseAll(new Utf8Source('xxxx'))->success);
        $this->assertFalse($grammar->parseAll(new Utf8Source('xxx'))->success);
        $this->assertSame(1, $calls);
    }

    public function testUndefinedFluentRuleFailsAtFreeze(): void
    {
        $rules = new Ruleset();
        $reference = $rules->rule('missing');
        $this->assertFalse($rules->defined('missing'));
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Undefined grammar rule: missing');
        $reference->freeze();
    }

    public function testVariadicChainsKeepNamedGroupsAndDetachObjectArguments(): void
    {
        $base = C::text('a')->then(C::text('b'))->named('pair');
        $object = new Text('c');
        $extended = $base->then($object, C::text('d'))->named('whole');
        $choice = $base->or($object, C::text('d'));
        $object->name('changed');

        $result = $extended->freeze()->parseAll(new Utf8Source('abcd'));
        $this->assertTrue($result->success);
        $this->assertSame('abcd', $result->ast()->rawText());
        $this->assertSame('pair', $result->ast()->children[0]->name);
        $this->assertCount(1, $result->ast()->children);
        $this->assertTrue($base->freeze()->parseAll(new Utf8Source('ab'))->success);
        $this->assertFalse($base->freeze()->parseAll(new Utf8Source('abcd'))->success);
        foreach (['ab', 'c', 'd'] as $input) {
            $this->assertTrue($choice->freeze()->parseAll(new Utf8Source($input))->success);
        }
        $this->assertFalse($choice->freeze()->parseAll(new Utf8Source('e'))->success);
        $this->assertSame($base, $base->then());
        $this->assertSame($base, $base->or());
    }

    public function testTerminalFactoriesPreserveBoundariesAndNumericConstraints(): void
    {
        $grammar = C::start()->then(C::nothing(), C::any(), C::integer(10, 15, 16), C::end())->freeze();
        $this->assertTrue($grammar->parseAll(new Utf8Source('éf'))->success);
        $this->assertFalse($grammar->parseAll(new Utf8Source('é9'))->success);
        $this->assertFalse($grammar->parseAll(new Utf8Source('éff'))->success);
        $this->assertFalse($grammar->matchPrefix(new Utf8Source('xéf'), 1)->success);
        $this->assertTrue(C::literalWindow('é')->freeze()->parseAll(new Utf8Source('é'))->success);
        $this->assertFalse(C::literalWindow('é')->freeze()->parseAll(new Utf8Source('e'))->success);
    }

    public function testChoicePoliciesAndScopedCaseSensitivity(): void
    {
        $a = C::text('a');
        $ab = C::text('ab');
        $source = new Utf8Source('ab');
        $this->assertSame('a', C::first($a, $ab)->freeze()->matchPrefix($source)->rawText());
        $this->assertSame('ab', C::longest($a, $ab)->freeze()->matchPrefix($source)->rawText());
        $this->assertSame('a', C::shortest($ab, $a)->freeze()->matchPrefix($source)->rawText());
        $this->assertSame('a', C::first($a, $ab)->prefer(Prefer::LONGEST)->freeze()->matchPrefix($source)->rawText());
        $this->assertSame('ab', C::choice($a, $ab)->prefer(Prefer::LONGEST)->freeze()->matchPrefix($source)->rawText());
        $sensitive = C::text('a')->caseSensitive();
        $insensitive = $sensitive->caseSensitive(false);
        // The inner override remains authoritative; use an unwrapped base for a different scope.
        $this->assertFalse($insensitive->freeze()->parseAll(new Utf8Source('A'))->success);
        $this->assertTrue($a->caseSensitive(false)->freeze()->parseAll(new Utf8Source('A'))->success);
        $this->assertFalse($sensitive->freeze()->parseAll(new Utf8Source('A'))->success);
    }

    public function testIntersectionExclusionAndNegativeLookahead(): void
    {
        $letter = C::range('a', 'z');
        $word = C::except($letter, C::text('x'));
        $this->assertTrue($word->freeze()->parseAll(new Utf8Source('a'))->success);
        $this->assertFalse($word->freeze()->parseAll(new Utf8Source('x'))->success);
        $both = C::all($letter, C::set('ab'));
        $this->assertTrue($both->freeze()->parseAll(new Utf8Source('b'))->success);
        $this->assertFalse($both->freeze()->parseAll(new Utf8Source('c'))->success);
        $probe = C::not(C::text('x'))->then(C::any())->freeze();
        $this->assertTrue($probe->parseAll(new Utf8Source('a'))->success);
        $this->assertFalse($probe->parseAll(new Utf8Source('x'))->success);
    }

    public function testRepetitionShortcutsRetainBoundsAndOriginalGrammar(): void
    {
        $letter = C::text('a');
        foreach (['star', 'kleene', 'plus', 'optional', 'maybe', 'some', 'many'] as $shortcut) {
            $grammar = $letter->$shortcut()->freeze();
            $this->assertSame(!in_array($shortcut, ['plus', 'some'], true), $grammar->parseAll(new Utf8Source(''))->success);
            $this->assertTrue($grammar->parseAll(new Utf8Source('a'))->success);
            $this->assertSame(in_array($shortcut, ['star', 'kleene', 'plus', 'some', 'many'], true), $grammar->parseAll(new Utf8Source('aa'))->success);
            $this->assertFalse($grammar->parseAll(new Utf8Source('b'))->success);
        }
        $this->assertFalse($letter->freeze()->parseAll(new Utf8Source('aa'))->success);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Nullable repetition');
        C::nothing()->maybe()->freeze();
    }

    public function testFacadeLifecycleIsLazyCachedValidatedAndCommittedOnce(): void
    {
        $reads = 0;
        $effects = [];
        $base = C::integer(0, 99);
        $number = $base->mapValue(static function (ParseResult $result) use (&$reads): int {
            ++$reads;
            return (int) $result->rawText();
        })->validateSemantic(static fn(ParseResult $result): bool => $result->value() >= 10)
            ->onCommit(static function (ParseResult $result) use (&$effects): void {
                $effects[] = $result->value();
            });
        $result = $number->freeze()->parseAll(new Utf8Source('42'));
        $this->assertSame(0, $reads);
        $this->assertSame([], $effects);
        $this->assertSame(42, $result->value());
        $this->assertSame(42, $result->value());
        $this->assertTrue($result->commit());
        $this->assertTrue($result->commit());
        $this->assertSame(1, $reads);
        $this->assertSame([42], $effects);
        $this->assertFalse($number->freeze()->parseAll(new Utf8Source('9'))->commit());
        $this->assertSame([42], $effects);
        $fallback = $number->then(C::text('!'))->or(C::text('42'))->freeze()->parseAll(new Utf8Source('42'));
        $this->assertTrue($fallback->commit());
        $this->assertSame(2, $reads);
        $this->assertSame([42], $effects);
        $this->assertSame('42', $base->freeze()->parseAll(new Utf8Source('42'))->value());
    }

    public function testGuardsDiagnosticsAndCollectionsRemainIndependent(): void
    {
        $base = C::range('a', 'z');
        $guarded = $base->guard(static fn(ConcreteNode $node): bool => $node->rawText() !== 'x');
        $this->assertTrue($base->freeze()->parseAll(new Utf8Source('x'))->success);
        $this->assertFalse($guarded->freeze()->parseAll(new Utf8Source('x'))->success);
        $result = C::sequence(
            C::text('a')->setResult('word')->pushResult('letters'),
            C::text('b')->concatResult('word')->pushResult('letters'),
        )->named('word')->freeze()->parseAll(new Utf8Source('ab'));
        $this->assertSame(['word' => 'ab', 'letters' => ['a', 'b']], $result->results());
        $this->assertSame('ab', $result->ast()->rawText());
        $failure = $base->diagnosticRule('letter')->freeze()->parseAll(new Utf8Source('0'));
        $this->assertContains(['letter'], $failure->diagnostic->rulePaths);
    }
}
