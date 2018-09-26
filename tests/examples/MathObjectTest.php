<?php

namespace Tests\example;

require_once 'ReversePolishNotationTrait.php';

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Stub;
use Vanderlee\Comprehend\Parser\Terminal\Range;

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
class MathObjectTest extends ParserTestCase
{

    use ReversePolishNotationTrait;

    /**
     * Math expression parser
     * @var Parser
     */
    private static $math;

    public static function setUpBeforeClass()
    {
        $term       = new Stub;
        $expression = new Stub;

        $integer            = (new Sequence(Repeat::kleene('-'), Repeat::plus(new Range('0', '9'))))->pushResult();
        $factor             = new Choice(
            ['(', $expression, ')'], $integer
        );
        $term->parser       = new Choice(
            (new Sequence($factor, '*', $term))->pushResult(null, 'multiply'),
            (new Sequence($factor, '/', $term))->pushResult(null, 'divide'), $factor
        );
        $expression->parser = new Choice(
            (new Sequence($term, '+', $expression))->pushResult(null, 'add'),
            (new Sequence($term, '-', $expression))->pushResult(null, 'subtract'), $term
        );

        self::$math = $expression;
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
            'Addition'                       => ['6+2', [6, 2, 'add'], 8],
            'Multiplication'                 => ['6*2', [6, 2, 'multiply'], 12],
            'Subtraction'                    => ['6-2', [6, 2, 'subtract'], 4],
            'Division'                       => ['6/2', [6, 2, 'divide'], 3],
            'Equal prio; Right-to-left #1'   => ['1+2-3', [1, 2, 3, 'subtract', 'add'], 0],
            'Equal prio; Right-to-left #2'   => ['1-2+3', [1, 2, 3, 'add', 'subtract'], -4],
            'Multiply before add #1'         => ['2*3+4', [2, 3, 'multiply', 4, 'add'], 10],
            'Multiply before add #2'         => ['2+3*4', [2, 3, 4, 'multiply', 'add'], 14],
            'Parenthesis before priority #1' => ['(2+3)*4', [2, 3, 'add', 4, 'multiply'], 20],
            //			'Parenthesis before priority #2' => ['2+(3*4)', [2, 3, 4, 'multiply', 'add'], 14],
        ];
    }

}
