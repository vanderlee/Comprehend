<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Syntax;

use Closure;

/** Instance-local cache of custom start resolution, never parser results. */
final class StartRegistry
{
    private array $cache = [];

    public function __construct(private readonly Catalog $catalog) {}

    public function factory(string $name): ?Closure
    {
        $name = Catalog::canonicalName($name);
        if (!array_key_exists($name, $this->cache)) {
            $this->cache[$name] = $this->catalog->start($name);
        }
        return $this->cache[$name];
    }
}
