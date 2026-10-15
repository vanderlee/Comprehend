<?php

namespace Tests\features\examples;

trait ReversePolishNotationTrait
{
    /**
     * Solve a reverse polish notation (postfix) expression.
     *
     * @param string[] $tokens
     *
     * @return float|int
     */
    protected function solveRpn(array $tokens): float|int
    {
        $stack = [];
        foreach ($tokens as $token) {
            if (!is_numeric($token)) {
                $operand_2 = array_pop($stack);
                $operand_1 = array_pop($stack);
                $result = $this->{'solveRpnOperator' . ucfirst($token)}($operand_1, $operand_2);
                $stack[] = $result;
            } else {
                $stack[] = $token;
            }
        }

        return array_pop($stack);
    }

    /** @noinspection PhpUnusedPrivateMethodInspection */
    protected function solveRpnOperatorAdd($operand_1, $operand_2)
    {
        return $operand_1 + $operand_2;
    }

    /** @noinspection PhpUnusedPrivateMethodInspection */
    protected function solveRpnOperatorSubtract($operand_1, $operand_2)
    {
        return $operand_1 - $operand_2;
    }

    /** @noinspection PhpUnusedPrivateMethodInspection */
    protected function solveRpnOperatorMultiply($operand_1, $operand_2): float|int
    {
        return $operand_1 * $operand_2;
    }

    /** @noinspection PhpUnusedPrivateMethodInspection */
    protected function solveRpnOperatorDivide($operand_1, $operand_2): float|int
    {
        return $operand_1 / $operand_2;
    }
}
