<?php

declare(strict_types=1);

namespace Tests\features\authoring;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Builder\FrozenGrammar;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Result\ParseResult;

class PublicFacadeTest extends TestCase
{
    public function testRulesetFactoriesAcceptFacadeArgumentsAndResultsWithoutExports(): void
    {
        $calls = 0;
        $rules = new Ruleset([], 'syntax');
        $rules->define('pair', static function (C $item, string $separator) use (&$calls): C {
            ++$calls;

            return $item->then($separator, $item);
        });
        $item = C::range('0', '9')->some()->named('digits');
        $rules->root($rules->rule('pair', $item, ':'));
        $this->assertSame(0, $calls);
        $grammar = $rules->freeze();
        $this->assertSame(1, $calls);
        $result = $grammar->parse('12:34');
        $this->assertTrue($result->success);
        $pair = $result->ast()->firstNamed('pair', 'syntax');
        $this->assertSame(
            ['12', '34'],
            array_map(static fn($node): string => $node->rawText(), $pair->allNamed('digits')),
        );
        $this->assertFalse($grammar->parse('12:')->success);
        $this->assertSame(1, $calls);
        $this->assertTrue($item->parse('56')->success);
    }

    public function testDefinitionsBuildIndependentFacadesAndKeepTheirLifecycle(): void
    {
        $effects = [];
        $definition = new Definition(C::text('a')->setResult('item')->onCommit(
            static function (ParseResult $result) use (&$effects): void {
                $effects[] = $result->rawText();
            },
        ));
        $definition->addProcessor('length', static fn(array $local): int => strlen($local['item']));
        $definition->addValidator(static fn(ParseResult $result): bool => $result->result('length') === 1);
        $built = $definition->build();
        $wrapped = C::of($definition);
        $this->assertSame($built, C::of($built));
        $definition->setGenerator(C::text('changed'));
        $result = C::sequence('(', $built, ')')->parse('(a)');
        $this->assertTrue($result->success);
        $this->assertSame(1, $result->result('length'));
        $this->assertSame([], $effects);
        $this->assertTrue($result->commit());
        $this->assertTrue($result->commit());
        $this->assertSame(['a'], $effects);
        $this->assertTrue($wrapped->parse('a')->success);
        $this->assertFalse($built->parse('changed')->success);
        $this->assertTrue($definition->build()->parse('changed')->success);
    }

    public function testDefinitionFactoriesAcceptFacadesAndRemainDeferred(): void
    {
        $calls = 0;
        $definition = new Definition(static function (C $item) use (&$calls): C {
            ++$calls;

            return $item->then($item);
        });
        $rules = new Ruleset(['double' => $definition]);
        $item = C::text('x');
        $grammar = $definition->build($item);
        $this->assertSame(0, $calls);
        $this->assertTrue($grammar->parse('xx')->success);
        $this->assertSame(1, $calls);
        $this->assertTrue($rules->rule('double', $item)->parse('xx')->success);
        $this->assertSame(2, $calls);
    }

    public function testFacadeAssignmentAndFrozenParsingRemainExplicit(): void
    {
        $assigned = 'original';
        $base = C::text('a');
        $grammar = FrozenGrammar::from($base->assignTo($assigned));
        $result = $grammar->parse('a');
        $this->assertSame('original', $assigned);
        $this->assertTrue($result->commit());
        $this->assertSame('a', $assigned);
        $assigned = 'reset';
        $this->assertFalse($grammar->parse('ab')->commit());
        $this->assertTrue($base->parse('a')->commit());
        $this->assertSame('reset', $assigned);
    }

    public function testFacadeReturningRecursiveFactoriesShareFrozenReferences(): void
    {
        $calls = 0;
        $rules = new Ruleset();
        $rules->define('nested', static function (string $text) use ($rules, &$calls): C {
            ++$calls;

            return C::first($text, C::sequence('(', $rules->rule('nested', $text), ')'));
        });
        $grammar = $rules->rule('nested', 'x')->freeze();
        $this->assertSame(1, $calls);
        $this->assertTrue($grammar->parse('((x))')->success);
        $this->assertFalse($grammar->parse('((y))')->success);
        $this->assertSame(1, $calls);
    }
}
