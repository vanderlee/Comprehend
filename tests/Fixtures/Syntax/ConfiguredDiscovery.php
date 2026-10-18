<?php

declare(strict_types=1);

namespace Tests\Fixtures\Syntax;

use Vanderlee\Comprehend\Syntax\SyntaxExtension;

final class ConfiguredDiscovery implements SyntaxExtension
{
    public string $configuration;

    public function __construct(string $configuration)
    {
        $this->configuration = $configuration;
    }

    public function rules(): iterable
    {
        return [];
    }

    public function fluents(): iterable
    {
        return [];
    }
}
