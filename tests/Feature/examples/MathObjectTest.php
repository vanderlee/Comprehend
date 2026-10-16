<?php

namespace Tests\Feature\examples;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Stub;
use Vanderlee\Comprehend\Parser\Terminal\Range;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;

require_once __DIR__ . '/ReversePolishNotationTrait.php';

abstract class MathObjectTest extends ParserTestCase
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
        $term = new Stub();
        $expression = new Stub();
        $integer = (new Sequence(Repeat::star(new Text('-')), Repeat::plus(new Range('0', '9'))))->pushResult();
        $factor = new Choice(new Sequence(new Text('('), $expression, new Text(')')), $integer);
        $term->setParser(
            new Choice(
                (new Sequence($factor, new Text('*'), $term))->pushResult(null, 'multiply'),
                (new Sequence($factor, new Text('/'), $term))->pushResult(null, 'divide'),
                $factor,
            ),
        );
        $expression->setParser(
            new Choice(
                (new Sequence($term, new Text('+'), $expression))->pushResult(null, 'add'),
                (new Sequence($term, new Text('-'), $expression))->pushResult(null, 'subtract'),
                $term,
            ),
        );
        self::$math = $expression;
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
            'Parenthesis before priority #1' => ['(2+3)*4', [2, 3, 'add', 4, 'multiply'], 20],
        ];
    }
}

uses(MathObjectTest::class);

test('expression', function ($expression, $expected, $result) {
    $match = self::$math->matchPrefix(new ByteSource($expression));
    $this->assertResult(true, strlen($expression), $match);
    $this->assertEquals($expected, $match->result());
    $this->assertEquals($result, $this->solveRpn($match->result()));
})->with(MathObjectTest::dataExpression());
