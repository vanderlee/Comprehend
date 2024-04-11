<?php

namespace Vanderlee\Comprehend\Core\Context;

use DomainException;
use Vanderlee\Comprehend\Directive\Prefer;

trait PreferContextTrait
{
    private $preference = [];

    /**
     * @param $preference
     *
     * @throws DomainException
     */
    private static function assertPreference($preference)
    {
        if (!in_array($preference, [
            Prefer::FIRST,
            Prefer::LONGEST,
            Prefer::SHORTEST,
        ])) {
            throw new DomainException('Invalid preference `' . $preference . '`');
        }
    }

    public function pushPreference($preference)
    {
        self::assertPreference($preference);

        $this->preference[] = $preference;
    }

    public function popPreference()
    {
        array_pop($this->preference);
    }

    public function getPreference()
    {
        return end($this->preference);
    }
}
