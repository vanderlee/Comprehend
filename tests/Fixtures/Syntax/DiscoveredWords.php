<?php

declare(strict_types=1);

namespace Tests\Fixtures\Syntax;

use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Syntax\SyntaxExtension;

final class DiscoveredWords implements SyntaxExtension
{
    public static int $constructions = 0;
    public static int $calls = 0;

    public function __construct()
    {
        ++self::$constructions;
    }

    public function rules(): iterable
    {
        yield 'discoveredWord' => static function (string $text = 'word'): C {
            ++self::$calls;

            return C::text($text);
        };
    }

    public function fluents(): iterable
    {
        yield 'discoveredWord' => static fn(C $item, string $text = '!'): C => $item->then($text);
    }

    /**
     * @param list<string> $rules
     * @param list<string> $fluents
     * @return array<string, mixed>
     */
    public static function manifest(
        string $class = self::class,
        array $rules = ['discoveredWord'],
        array $fluents = ['discoveredWord'],
    ): array {
        return ['extra' => ['comprehend' => ['extensions' => [compact('class', 'rules', 'fluents')]]]];
    }
}
