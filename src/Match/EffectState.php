<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Match;

/** @internal State of a selected match's effect sequence. */
enum EffectState
{
    case Pending;
    case Running;
    case Completed;
    case Failed;
}
