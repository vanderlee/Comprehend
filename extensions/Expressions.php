<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions;

use Closure;
use InvalidArgumentException;
use LogicException;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Expression\Operator;
use Vanderlee\Comprehend\Extensions\Expression\OperatorKind;
use Vanderlee\Comprehend\Extensions\Expression\ReductionSequence;
use Vanderlee\Comprehend\Extensions\Expression\ReductionList;
use Vanderlee\Comprehend\Extensions\Expression\ValueChoice;
use Vanderlee\Comprehend\Extensions\Expression\ValueRepeat;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Syntax\Catalog;
use Vanderlee\Comprehend\Syntax\SyntaxExtension;

/** Optional precedence-table authoring; all recognition delegates to the existing combinator runtime. */
final class Expressions implements SyntaxExtension
{
    public function fluents(): iterable
    {
        return $this->rules();
    }

    public function rules(): iterable
    {
        yield 'operatorExpression' => self::expression(...);
        yield 'expressionChoice' => self::choice(...);
    }

    private static function choice(C|Parser|string ...$alternatives): Parser
    {
        return new ValueChoice(...array_map(Catalog::parser(...), $alternatives));
    }

    private static function expression(C|Parser|string $primary, Operator ...$operators): Parser
    {
        $operand = Catalog::parser($primary);
        $levels = [];
        foreach ($operators as $operator) {
            $levels[$operator->precedence][] = $operator;
        }
        krsort($levels, SORT_NUMERIC);
        foreach ($levels as $level) {
            $kind = $level[0]->kind;
            foreach ($level as $operator) {
                if ($operator->kind !== $kind) {
                    throw new InvalidArgumentException('Operators at one precedence must have the same kind');
                }
            }
            $operand = match ($kind) {
                OperatorKind::PREFIX => self::prefix($operand, $level),
                OperatorKind::POSTFIX => self::postfix($operand, $level),
                default => self::infix($operand, $level, $kind),
            };
        }

        return $operand;
    }

    /** @param non-empty-list<Operator> $operators */
    private static function bindings(array $operators): Parser
    {
        $bindings = [];
        foreach ($operators as $operator) {
            $reduce = $operator->reduce;
            // Exactly one consuming operator, even when its neighboring operand consumes input.
            $bindings[] = new ReductionSequence(
                static fn(array $children): Closure => $reduce,
                new Repeat($operator->parser(), 1, 1),
            );
        }

        return new ValueChoice(...$bindings);
    }

    /** @param non-empty-list<Operator> $operators */
    private static function prefix(Parser $operand, array $operators): Parser
    {
        return new ReductionList(
            static function (array $children): mixed {
                if (count($children) === 1) {
                    return $children[0]->value();
                }
                $operators = self::unaryReducers($children[0]);
                $value = $children[1]->value();
                for ($index = count($operators) - 1; $index >= 0; --$index) {
                    $value = $operators[$index]($value);
                }

                return $value;
            },
            $operand,
            new ValueRepeat(self::bindings($operators), 1),
            max: 1,
            allowLeading: true,
        );
    }

    /** @param non-empty-list<Operator> $operators */
    private static function postfix(Parser $operand, array $operators): Parser
    {
        return new ReductionList(
            static function (array $children): mixed {
                $value = $children[0]->value();
                if (count($children) > 1) {
                    foreach (self::unaryReducers($children[1]) as $operator) {
                        $value = $operator($value);
                    }
                }

                return $value;
            },
            $operand,
            new ValueRepeat(self::bindings($operators), 1),
            max: 1,
            allowTrailing: true,
        );
    }

    /** @param non-empty-list<Operator> $operators */
    private static function infix(Parser $operand, array $operators, OperatorKind $kind): Parser
    {
        return new ReductionList(
            static function (array $children) use ($kind): mixed {
                $last = count($children) - 1;
                if ($kind === OperatorKind::RIGHT) {
                    $value = $children[$last]->value();
                    for ($index = $last - 1; $index > 0; $index -= 2) {
                        $reduce = self::reducer($children[$index]);
                        $value = $reduce($children[$index - 1]->value(), $value);
                    }

                    return $value;
                }
                $value = $children[0]->value();
                for ($index = 1; $index < $last; $index += 2) {
                    $reduce = self::reducer($children[$index]);
                    $value = $reduce($value, $children[$index + 1]->value());
                }

                return $value;
            },
            $operand,
            self::bindings($operators),
            max: $kind === OperatorKind::NON_ASSOCIATIVE ? 2 : null,
        );
    }

    private static function reducer(ParseResult $binding): Closure
    {
        $reduce = $binding->value();
        if (!$reduce instanceof Closure) {
            throw new LogicException('Expression operator binding must contain a reducer');
        }

        return $reduce;
    }

    /** @return list<Closure> */
    private static function unaryReducers(ParseResult $bindings): array
    {
        $values = $bindings->value();
        if (!is_array($values)) {
            throw new LogicException('Expression unary bindings must contain a list of reducers');
        }
        $reducers = [];
        foreach ($values as $value) {
            if (!$value instanceof Closure) {
                throw new LogicException('Expression unary binding must contain a reducer');
            }
            $reducers[] = $value;
        }

        return $reducers;
    }
}
