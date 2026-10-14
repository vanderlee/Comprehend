<?php

declare (strict_types=1);

namespace Vanderlee\Comprehend\Source;

enum SourceMode: string
{
    case BYTES = 'bytes';
    case UTF8 = 'utf8';
}
