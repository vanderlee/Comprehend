<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Builder\FreezeFlags;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;

test('generated composed grammars agree across execution paths source modes and byte offsets', function (
    int $seed,
    bool $unicode,
): void {
    $alphabet = $unicode ? ['a', "\u{e9}", "\u{1f642}"] : ['a', 'b', 'c'];
    // Stable hashes make every failing grammar reproducible without ambient random state.
    $build = static function (string $path, int $depth) use (&$build, $alphabet, $seed): array {
        $hash = hash('sha256', $seed . ':' . $path, true);
        $literal = $alphabet[ord($hash[0]) % count($alphabet)];
        if ($depth === 0) {
            return [(new Text($literal))->name($path)->tag('terminal')->pushResult('units'), $literal];
        }
        [$left, $leftText] = $build($path . 'L', $depth - 1);
        [$right, $rightText] = $build($path . 'R', $depth - 1);
        return match (ord($hash[1]) % 3) {
            0 => [new Sequence($left, new Text(','), $right), $leftText . ',' . $rightText],
            1 => [new Choice($left, $right), $leftText],
            2 => [new Repeat($left, 1, 3), $leftText . $leftText],
        };
    };
    [$parser, $witness] = $build('root', 2);
    // The closing marker prevents a variable-length prefix from masking malformed input.
    $parser = new Sequence(new Text('['), $parser, new Text(']'));
    $valid = '[' . $witness . ']';
    foreach ([ByteSource::class, Utf8Source::class] as $sourceClass) {
        foreach (['', "\u{e9}:"] as $prefix) {
            foreach ([$valid, $valid . '!', substr($valid, 0, -1), '[!]'] as $input) {
                $source = new $sourceClass($prefix . $input);
                foreach (['parseAll', 'matchPrefix'] as $operation) {
                    $baseline = null;
                    $grammars = [$parser, $parser->freeze(FreezeFlags::NONE), $parser->freeze(), C::of($parser)];
                    foreach ($grammars as $grammar) {
                        $result = $grammar->$operation($source, strlen($prefix));
                        if ($input === $valid) {
                            expect($result->success)->toBeTrue();
                            expect($result->rawText())->toBe($valid);
                            expect([$result->start, $result->end])->toBe([
                                strlen($prefix), strlen($prefix . $valid),
                            ]);
                        } elseif ($operation === 'parseAll' || $input !== $valid . '!') {
                            expect($result->success)->toBeFalse();
                        } else {
                            expect($result->success)->toBeTrue();
                            expect($result->rawText())->toBe($valid);
                        }
                        $observed = [
                            $result->success, $result->consumedByteLength, $result->farthestFailure,
                            $result->diagnostic?->message(), $result->node?->toArray(), $result->ast()?->toArray(),
                            $result->value(), $result->results(), $result->validate()->valid,
                        ];
                        $baseline ??= $observed;
                        expect($observed)->toEqual($baseline);
                    }
                }
            }
        }
    }
})->with(range(0, 7))->with([false, true]);
