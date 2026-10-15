<?php

/** @noinspection PhpUndefinedMethodInspection */
/** @noinspection PhpUndefinedFieldInspection */

namespace Tests\features\examples;

use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Parser\Terminal\Text;

require_once __DIR__ . '/ReversePolishNotationTrait.php';
use Tests\ParserTestCase;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Range;

abstract class MathRulesetTest extends ParserTestCase
{
    use ReversePolishNotationTrait;

    /**
     * Math expression parser.
     *
     * @var Parser
     */
    protected static $math;

    public static function setUpBeforeClass(): void
    {
        $rules = new Ruleset();
        $literal = static fn(string $text) => new Text($text);
        $rules->define(
            'integer',
            (new Sequence(Repeat::optional($literal('-')), Repeat::plus(new Range('0', '9'))))->pushResult(),
        );
        $rules->define(
            'factor',
            Choice::first(
                new Sequence($literal('('), $rules->ref('expression'), $literal(')')),
                $rules->ref('integer'),
            ),
        );
        $rules->define(
            'term',
            Choice::first(
                (new Sequence($rules->ref('factor'), $literal('*'), $rules->ref('term')))->pushResult(null, 'multiply'),
                (new Sequence($rules->ref('factor'), $literal('/'), $rules->ref('term')))->pushResult(null, 'divide'),
                $rules->ref('factor'),
            ),
        );
        $rules->define(
            'expression',
            Choice::first(
                (new Sequence($rules->ref('term'), $literal('+'), $rules->ref('expression')))->pushResult(null, 'add'),
                (new Sequence($rules->ref('term'), $literal('-'), $rules->ref('expression')))->pushResult(
                    null,
                    'subtract',
                ),
                $rules->ref('term'),
            ),
        );
        $rules->define(Ruleset::ROOT, $rules->ref('expression'));
        self::$math = $rules;
    }

    public static function dataExpression()
    {
        return [
            'Addition' => ['6+2', [6, 2, 'add'], 8],
            'Multiplication' => ['6*2', [6, 2, 'multiply'], 12],
            'Subtraction' => ['6-2', [6, 2, 'subtract'], 4],
            'Division' => ['6/2', [6, 2, 'divide'], 3],
            'Equal prio; Right-to-left #1' => ['1+2-3', [1, 2, 3, 'subtract', 'add'], 0],
            'Equal prio; Right-to-left #2' => ['1-2+3', [1, 2, 3, 'add', 'subtract'], -4],
            'Multiply before add #1' => ['2*3+4', [2, 3, 'multiply', 4, 'add'], 10],
            'Multiply before add #2' => ['2+3*4', [2, 3, 4, 'multiply', 'add'], 14],
            'Parenthesis before priority' => ['(2+3)*4', [2, 3, 'add', 4, 'multiply'], 20],
        ];
    }
}

uses(MathRulesetTest::class);

test('expression', function ($expression, $expected, $result) {
    $match = self::$math->matchPrefix(new ByteSource($expression));
    $this->assertResult(true, strlen($expression), $match);
    $this->assertEquals($expected, $match->result());
    $this->assertEquals($result, $this->solveRpn($match->result()));
})->with(MathRulesetTest::dataExpression());
