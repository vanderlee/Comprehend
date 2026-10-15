<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Runtime;

/** Choice ranking shared by authoring and recognition scopes. */
enum ChoicePolicy: string
{
    case FIRST = 'first';
    case LONGEST = 'longest';
    case SHORTEST = 'shortest';
}
