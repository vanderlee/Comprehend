<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Vanderlee\Comprehend\Builder\AbstractSpecification;
use Vanderlee\Comprehend\Comprehend as C;

final class ExampleSpecification extends AbstractSpecification
{
    protected const ROOT_RULE = 'entry';

    public static int $builds = 0;

    protected function definitions(): array
    {
        ++self::$builds;

        return [
            'entry' => $this->rule('item')->then('!'),
            'item' => C::text('a'),
            'alternate' => C::text('b'),
        ];
    }
}
