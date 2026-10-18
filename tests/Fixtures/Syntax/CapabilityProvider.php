<?php

declare(strict_types=1);

namespace Tests\Fixtures\Syntax;

use RuntimeException;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Syntax\ExtensionLoader;
use Vanderlee\Comprehend\Syntax\SyntaxExtension;

final class CapabilityProvider implements SecondaryCapability, SyntaxExtension
{
    public static int $constructions = 0;
    public static bool $broken = false;
    public static ?ExtensionLoader $recursiveLoader = null;
    private int $id;

    public function __construct()
    {
        $this->id = ++self::$constructions;
        self::$recursiveLoader?->requireOne(PrimaryCapability::class);
    }

    public function identity(): int
    {
        return $this->id;
    }

    public function secondaryIdentity(): int
    {
        return $this->id;
    }

    public function rules(): iterable
    {
        yield 'identity' => fn(): C => C::text((string) $this->id);
    }

    public function fluents(): iterable
    {
        if (self::$broken) {
            throw new RuntimeException('fluent installation failed');
        }

        return [];
    }

    public static function manifest(array $entry, string $source = 'fixture.extension.json'): array
    {
        return [
            '_comprehend_manifest_path' => $source,
            'extra' => ['comprehend' => ['extensions' => [$entry]]],
        ];
    }
}
