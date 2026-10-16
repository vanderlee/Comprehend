<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions;

use InvalidArgumentException;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Syntax\SyntaxExtension;

/** Optional ASCII identifier factories composed entirely from the existing parser graph. */
final class AsciiIdentifiers implements SyntaxExtension
{
    public function fluents(): iterable
    {
        yield 'asciiIdentifier' => static fn(C $item): C => $item->then(self::identifier());
        yield 'asciiKeyword' => static fn(C $item, string $word): C => $item->then(self::keyword($word));
    }

    public function rules(): iterable
    {
        yield 'asciiIdentifier' => self::identifier(...);
        yield 'asciiKeyword' => self::keyword(...);
    }

    private static function identifier(): C
    {
        // Keep ASCII membership fixed even inside case-insensitive Unicode grammars.
        return C::regex('/[A-Za-z_][A-Za-z0-9_]*/A')->caseSensitive();
    }

    private static function keyword(string $word): C
    {
        if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $word) !== 1) {
            throw new InvalidArgumentException('Invalid ASCII keyword');
        }
        $continuation = C::regex('/[A-Za-z0-9_]/A')->caseSensitive();

        // Check the immediate next unit, before enclosing spacing can consume anything.
        return C::text($word)->thenIgnore(C::not($continuation))->lexeme();
    }
}
