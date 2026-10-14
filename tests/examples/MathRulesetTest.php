<?php

/** @noinspection PhpUndefinedMethodInspection */
/** @noinspection PhpUndefinedFieldInspection */
namespace Tests\example;

use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Parser\Terminal\Text;
require_once 'ReversePolishNotationTrait.php';
use Tests\ParserTestCase;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Range;
/**
 * Example of a simple maths parser, constructed using basic objects.
 *
 * expression    ::= term '+' expression | term '-' expression | term
 * term            ::= factor '*' term | factor '/' term | factor
 * factor        ::= '(' expression ')' | integer
 * integer        ::= '-'? [0-9]+
 *
 * @author Martijn
 */
class MathRulesetTest extends ParserTestCase
{
    use ReversePolishNotationTrait;
    /**
     * Math expression parser.
     *
     * @var Parser
     */
    private static $math;
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
            Choice::first(new Sequence($literal('('), $rules->rule('expression'), $literal(')')), $rules->rule('integer')),
        );
        $rules->define(
            'term',
            Choice::first(
                (new Sequence($rules->rule('factor'), $literal('*'), $rules->rule('term')))->pushResult(null, 'multiply'),
                (new Sequence($rules->rule('factor'), $literal('/'), $rules->rule('term')))->pushResult(null, 'divide'),
                $rules->rule('factor'),
            ),
        );
        $rules->define(
            'expression',
            Choice::first(
                (new Sequence($rules->rule('term'), $literal('+'), $rules->rule('expression')))->pushResult(null, 'add'),
                (new Sequence($rules->rule('term'), $literal('-'), $rules->rule('expression')))->pushResult(null, 'subtract'),
                $rules->rule('term'),
            ),
        );
        $rules->define(Ruleset::ROOT, $rules->rule('expression'));
        self::$math = $rules;
    }
    /**
     * @dataProvider dataExpression
     *
     * @param $expression
     * @param $expected
     * @param $result
     */
    public function testExpression($expression, $expected, $result)
    {
        $match = self::$math->matchPrefix(new ByteSource($expression));
        $this->assertResult(true, strlen($expression), $match);
        $this->assertEquals($expected, $match->result());
        $this->assertEquals($result, $this->solveRpn($match->result()));
    }
    public function dataExpression()
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
