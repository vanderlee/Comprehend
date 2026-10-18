<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Expression;

use Closure;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Terminal\Text;

/** Immutable operator syntax, precedence and lazy semantic reduction. Higher precedence binds tighter. */
final readonly class Operator
{
    private C $syntax;
    public Closure $reduce;

    private function __construct(
        C|Parser|string $syntax,
        public int $precedence,
        public OperatorKind $kind,
        callable $reduce,
    ) {
        $this->syntax = C::of(is_string($syntax) ? new Text($syntax) : $syntax);
        $this->reduce = Closure::fromCallable($reduce);
    }

    public static function prefix(C|Parser|string $syntax, int $precedence, callable $reduce): self
    {
        return new self($syntax, $precedence, OperatorKind::PREFIX, $reduce);
    }

    public static function postfix(C|Parser|string $syntax, int $precedence, callable $reduce): self
    {
        return new self($syntax, $precedence, OperatorKind::POSTFIX, $reduce);
    }

    public static function infixLeft(C|Parser|string $syntax, int $precedence, callable $reduce): self
    {
        return new self($syntax, $precedence, OperatorKind::LEFT, $reduce);
    }

    public static function infixRight(C|Parser|string $syntax, int $precedence, callable $reduce): self
    {
        return new self($syntax, $precedence, OperatorKind::RIGHT, $reduce);
    }

    public static function infixNone(C|Parser|string $syntax, int $precedence, callable $reduce): self
    {
        return new self($syntax, $precedence, OperatorKind::NON_ASSOCIATIVE, $reduce);
    }

    /** Detached syntax for graph construction; later caller mutations cannot alter this definition. */
    public function parser(): Parser
    {
        return $this->syntax->parser();
    }
}
