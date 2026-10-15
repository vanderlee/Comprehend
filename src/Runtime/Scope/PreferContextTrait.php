<?php

declare (strict_types=1);

namespace Vanderlee\Comprehend\Runtime\Scope;

use DomainException;
use Vanderlee\Comprehend\Directive\Prefer;

trait PreferContextTrait
{
    /**
     * @var list<string>
     */
    private array $preference = [];

    public function pushPreference(string $preference): void
    {
        self::assertPreference($preference);
        $this->preference[] = $preference;
    }

    /**
     * @throws DomainException
     */
    private static function assertPreference(string $preference): void
    {
        if (!in_array($preference, [Prefer::FIRST, Prefer::LONGEST, Prefer::SHORTEST], true)) {
            throw new DomainException('Invalid preference `' . $preference . '`');
        }
    }

    public function popPreference(): void
    {
        array_pop($this->preference);
    }

    public function getPreference(): string
    {
        return end($this->preference);
    }
}
