<?php

declare(strict_types=1);

namespace Tests\Fixtures\Syntax;

use RuntimeException;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Syntax\SyntaxExtension;

final class BrokenDiscovery implements SyntaxExtension
{
    public static bool $broken = true;

    public function rules(): iterable
    {
        yield 'discoveredWord' => static fn(): C => C::text('ok');
    }

    public function fluents(): iterable
    {
        if (self::$broken) {
            throw new RuntimeException('fluent registration failed');
        }

        return [];
    }
}
