<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Compatibility;

enum CoordinatePolicy: string
{
    case ASCII = 'ascii';
    case BYTES = 'bytes';
    case CODE_POINTS = 'code_points';
}
