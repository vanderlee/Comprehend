<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Library\Internal;

/** The YAML 1.2.2 c-printable set, independent of the source's recognition unit. @internal */
final class YamlCharacters
{
    public static function valid(string $text): bool
    {
        return mb_check_encoding($text, 'UTF-8')
            && preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]|\xc2[\x80-\x84\x86-\x9f]|'
                . '\xef\xbf[\xbe\xbf]/', $text) === 0;
    }
}
