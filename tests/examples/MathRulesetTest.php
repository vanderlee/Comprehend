<?php

namespace tests\example;

require_once 'ReversePolishNotationTrait.php';

use tests\ParserTestCase;
use vanderlee\comprehend\builder\Ruleset;
use vanderlee\comprehend\parser\Parser;
use vanderlee\comprehend\parser\structure\Choice;
use vanderlee\comprehend\parser\structure\Repeat;
use vanderlee\comprehend\parser\structure\Sequence;
use vanderlee\comprehend\parser\terminal\Range;

/**
 * Example of a simple maths parser, constructed using basic objects
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
     * Math expression parser
     * @var Parser
     */
    private static $math;

    public static function setUpBeforeClass()
    {
        self::$math = new Ruleset([
            's'      => Sequence::class,
            'r'      => Range::class,
            'opt'    => function ($parser) {
                return new Repeat($parser, 0, 1);
            },
            'plus'   => function ($parser) {
                return new Repeat($parser, 1);
            },
            'kleene' => Repeat::class,
            'choice' => Choice::class,
        ]);

        self::$math->define([
            'integer'    => self::$math->s(
                self::$math->opt('-'),
                self::$math->plus(
                    self::$math->r('0', '9')
                )
            )->pushResult(),
            'factor'     => self::$math->choice(self::$math->s('(', self::$math->expression, ')'), self::$math->integer),
            'term'       => self::$math->choice(
                self::$math->s(self::$math->factor, '*', self::$math->term)->pushResult(null, 'multiply'),
                self::$math->s(self::$math->factor, '/', self::$math->term)->pushResult(null, 'divide'),
                self::$math->factor
            ),
            'expression' => self::$math->choice(
                self::$math->s(self::$math->term, '+', self::$math->expression)->pushResult(null, 'add'),
                self::$math->s(self::$math->term, '-', self::$math->expression)->pushResult(null, 'subtract'),
                self::$math->term
            ),
        ]);

        self::$math->define(Ruleset::DEFAULT, 'expression');
    }

    /**
     * @dataProvider dataExpression
     */
    public function testExpression($expression, $expected, $result)
    {
        $match = self::$math->match($expression);
        $this->assertResult(true, strlen($expression), $match);
        $this->assertEquals($expected, $match->result);
        $this->assertEquals($result, $this->solveRpn($match->result));
    }

    public function dataExpression()
    {
        return [
            'Addition'                     => ['6+2', [6, 2, 'add'], 8],
            'Multiplication'               => ['6*2', [6, 2, 'multiply'], 12],
            'Subtraction'                  => ['6-2', [6, 2, 'subtract'], 4],
            'Division'                     => ['6/2', [6, 2, 'divide'], 3],
            'Equal prio; Right-to-left #1' => ['1+2-3', [1, 2, 3, 'subtract', 'add'], 0],
            'Equal prio; Right-to-left #2' => ['1-2+3', [1, 2, 3, 'add', 'subtract'], -4],
            'Multiply before add #1'       => ['2*3+4', [2, 3, 'multiply', 4, 'add'], 10],
            'Multiply before add #2'       => ['2+3*4', [2, 3, 4, 'multiply', 'add'], 14],
            'Parenthesis before priority'  => ['(2+3)*4', [2, 3, 'add', 4, 'multiply'], 20],
        ];
    }

}
