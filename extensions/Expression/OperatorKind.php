<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Expression;

/** One extraction behavior per precedence level. */
enum OperatorKind
{
    case PREFIX;
    case POSTFIX;
    case LEFT;
    case RIGHT;
    case NON_ASSOCIATIVE;
}
