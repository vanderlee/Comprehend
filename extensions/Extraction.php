<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions;

use Vanderlee\Comprehend\Comprehend;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Syntax;
use Vanderlee\Comprehend\Syntax\Catalog;
use Vanderlee\Comprehend\Syntax\SyntaxExtension;
use Vanderlee\Comprehend\Extensions\Extraction\Parser\Collect;
use Vanderlee\Comprehend\Extensions\Extraction\Parser\Record;
use Vanderlee\Comprehend\Extensions\Extraction\Parser\ValueChoice;
use Vanderlee\Comprehend\Extensions\Extraction\Parser\ValueRepeat;
use Vanderlee\Comprehend\Extensions\Extraction\Parser\OptionalValue;
use Vanderlee\Comprehend\Extensions\Extraction\Parser\MapWith;
use Vanderlee\Comprehend\Extensions\Extraction\Parser\FoldLeft;

/** Optional application-value authoring over the shared recognition and projection runtime. */
final class Extraction implements SyntaxExtension
{
    public static function syntax(): Syntax
    {
        return Syntax::create()->extend(new self());
    }

    public function rules(): iterable
    {
        yield 'record' => self::record(...);
        yield 'collect' => static fn(Comprehend|Parser|string ...$parsers): Parser => new Collect(
            ...array_values(array_map(Catalog::parser(...), $parsers)),
        );
        yield 'valuechoice' => static fn(Comprehend|Parser|string ...$parsers): Parser => new ValueChoice(
            ...array_values(array_map(Catalog::parser(...), $parsers)),
        );
        yield 'repeatvalues' => static fn(
            Comprehend|Parser|string $parser,
            int $min = 0,
            ?int $max = null,
        ): Parser => new ValueRepeat(Catalog::parser($parser), $min, $max);
        yield 'manyvalues' => static fn(Comprehend|Parser|string $parser): Parser => new ValueRepeat(
            Catalog::parser($parser),
        );
        yield 'somevalues' => static fn(Comprehend|Parser|string $parser): Parser => new ValueRepeat(
            Catalog::parser($parser),
            1,
        );
        yield 'optionalvalue' => static fn(
            Comprehend|Parser|string $parser,
            mixed $default = null,
        ): Parser => new OptionalValue(Catalog::parser($parser), $default);
    }

    public function fluents(): iterable
    {
        yield 'collect' => static fn(Comprehend $item, Comprehend|Parser|string ...$next): Parser => new Collect(
            $item->parser(),
            ...array_values(array_map(Catalog::parser(...), $next)),
        );
        yield 'valuechoice' => static fn(
            Comprehend $item,
            Comprehend|Parser|string ...$next,
        ): Parser => new ValueChoice(
            $item->parser(),
            ...array_values(array_map(Catalog::parser(...), $next)),
        );
        yield 'map' => static fn(Comprehend $item, callable $mapper): Parser => $item->parser()->map($mapper);
        yield 'mapwith' => static fn(
            Comprehend $item,
            Comprehend|Parser|string $next,
            callable $combine,
        ): Parser => new MapWith($item->parser(), Catalog::parser($next), $combine);
        yield 'foldleft' => static fn(
            Comprehend $item,
            Comprehend|Parser|string $step,
            callable $combine,
            int $min = 0,
            ?int $max = null,
        ): Parser => new FoldLeft($item->parser(), Catalog::parser($step), $combine, $min, $max);
        yield 'repeatvalues' => static fn(Comprehend $item, int $min = 0, ?int $max = null): Parser => new ValueRepeat(
            $item->parser(),
            $min,
            $max,
        );
        yield 'manyvalues' => static fn(Comprehend $item): Parser => new ValueRepeat($item->parser());
        yield 'somevalues' => static fn(Comprehend $item): Parser => new ValueRepeat($item->parser(), 1);
        yield 'optionalvalue' => static fn(Comprehend $item, mixed $default = null): Parser => new OptionalValue(
            $item->parser(),
            $default,
        );
        yield 'maptext' => static fn(Comprehend $item, callable $mapper): Parser => $item->parser()->mapValue(
            static fn(ParseResult $result): mixed => $mapper($result->rawText()),
        );
    }

    /** @param array<array-key, Comprehend|Parser|string> $fields */
    private static function record(array $fields): Record
    {
        $parsers = [];
        foreach ($fields as $key => $field) {
            $parsers[$key] = Catalog::parser($field);
        }

        return new Record($parsers);
    }
}
