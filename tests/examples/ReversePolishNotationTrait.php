<?php

namespace tests\example;

trait ReversePolishNotationTrait
{

    private function solveRpn(array $tokens)
    {
        $stack = [];

        foreach ($tokens as $token) {
            if (!is_numeric($token)) {
                $operand_2 = array_pop($stack);
                $operand_1 = array_pop($stack);
                $result    = call_user_func([$this, 'solveRpnOperator' . ucfirst($token)], $operand_1, $operand_2);
                array_push($stack, $result);
            } else {
                array_push($stack, $token);
            }
        }
        $result = array_pop($stack);

        return $result;
    }

    private function solveRpnOperatorAdd($operand_1, $operand_2)
    {
        return $operand_1 + $operand_2;
    }

    private function solveRpnOperatorSubtract($operand_1, $operand_2)
    {
        return $operand_1 - $operand_2;
    }

    private function solveRpnOperatorMultiply($operand_1, $operand_2)
    {
        return $operand_1 * $operand_2;
    }

    private function solveRpnOperatorDivide($operand_1, $operand_2)
    {
        return $operand_1 / $operand_2;
    }

}
