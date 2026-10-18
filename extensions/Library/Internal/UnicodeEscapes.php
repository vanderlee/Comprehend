<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Library\Internal;

/** Unicode-scalar checks for grammars that already recognize escape spelling. @internal */
final class UnicodeEscapes
{
    public static function valid(string $text, bool $surrogatePairs): bool
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            return false;
        }
        for ($index = 0; $index < strlen($text); $index++) {
            if ($text[$index] !== '\\') {
                continue;
            }
            $escape = $text[++$index] ?? '';
            if ($escape !== 'u' && $escape !== 'U') {
                continue;
            }
            $width = $escape === 'u' ? 4 : 8;
            $code = hexdec(substr($text, $index + 1, $width));
            $index += $width;
            if ($code >= 0xd800 && $code <= 0xdbff && $surrogatePairs && $escape === 'u') {
                if (substr($text, $index + 1, 2) !== '\\u') {
                    return false;
                }
                $low = hexdec(substr($text, $index + 3, 4));
                if ($low < 0xdc00 || $low > 0xdfff) {
                    return false;
                }
                $index += 6;
            } elseif ($code > 0x10ffff || ($code >= 0xd800 && $code <= 0xdfff)) {
                return false;
            }
        }

        return true;
    }
}
