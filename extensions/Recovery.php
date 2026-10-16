<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions;

use InvalidArgumentException;
use Vanderlee\Comprehend\Comprehend;
use Vanderlee\Comprehend\Extensions\Recovery\Parser\RecoverDelimited;
use Vanderlee\Comprehend\Extensions\Recovery\Parser\RecoverItem;
use Vanderlee\Comprehend\Extensions\Recovery\Parser\RecoverUntil;
use Vanderlee\Comprehend\Extensions\Recovery\Parser\RecoverWith;
use Vanderlee\Comprehend\Extensions\Recovery\RecoveryContext;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Source\Span;
use Vanderlee\Comprehend\Syntax\Catalog;
use Vanderlee\Comprehend\Syntax\SyntaxExtension;

/** Optional error recovery on the shared typed parser graph and result lifecycle. */
final class Recovery implements SyntaxExtension
{
    public function rules(): iterable
    {
        yield 'recoverUntil' => self::recoverUntil(...);
        yield 'recoverDelimited' => self::recoverDelimited(...);
        yield 'recoverItem' => self::recoverItem(...);
        yield 'recoverWith' => self::recoverWith(...);
    }

    public function fluents(): iterable
    {
        return $this->rules();
    }

    private static function recoverUntil(
        Comprehend|Parser|string $parser,
        Comprehend|Parser|string $boundary,
        bool $consumeBoundary = false,
        bool $allowEof = false,
        ?int $maxSkippedBytes = null,
    ): Parser {
        return new RecoverUntil(
            Catalog::parser($parser),
            Catalog::parser($boundary),
            $consumeBoundary,
            $allowEof,
            $maxSkippedBytes,
        );
    }
    private static function recoverDelimited(
        Comprehend|Parser|string $parser,
        Comprehend|Parser|string $open,
        Comprehend|Parser|string $close,
        Comprehend|Parser|null $ignore = null,
        bool $allowEof = false,
        ?int $maxSkippedBytes = null,
    ): Parser {
        return new RecoverDelimited(
            Catalog::parser($parser),
            Catalog::parser($open),
            Catalog::parser($close),
            $ignore === null ? null : Catalog::parser($ignore),
            $allowEof,
            $maxSkippedBytes,
        );
    }

    private static function recoverItem(
        Comprehend|Parser|string $item,
        Comprehend|Parser|string $separator,
        Comprehend|Parser|string $close,
        int $min = 0,
        ?int $max = null,
        bool $allowTrailing = false,
        Comprehend|Parser|null $ignore = null,
        ?int $maxSkippedBytes = null,
    ): Parser {
        return new RecoverItem(
            Catalog::parser($item),
            Catalog::parser($separator),
            Catalog::parser($close),
            $min,
            $max,
            $allowTrailing,
            $ignore === null ? null : Catalog::parser($ignore),
            $maxSkippedBytes,
        );
    }

    /**
     * @param callable(RecoveryContext): ?Span $strategy
     * @param array<array-key, mixed> $probes
     */
    private static function recoverWith(
        Comprehend|Parser|string $parser,
        callable $strategy,
        array $probes = [],
        ?int $maxSkippedBytes = null,
    ): Parser {
        $parsers = [];
        foreach ($probes as $name => $probe) {
            if (
                !is_string($name) || $name === ''
                || !($probe instanceof Comprehend || $probe instanceof Parser || is_string($probe))
            ) {
                throw new InvalidArgumentException('Recovery probes require names and parser expressions');
            }
            $parsers[$name] = Catalog::parser($probe);
        }

        return new RecoverWith(Catalog::parser($parser), $strategy, $parsers, $maxSkippedBytes);
    }
}
