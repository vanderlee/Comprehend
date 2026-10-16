<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Parser\Terminal\Set;
use Vanderlee\Comprehend\Parser\Terminal\Regex;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Utf8Source;

$rows = [];
$measure = static function (string $name, int $bytes, callable $run) use (&$rows): void {
    for ($i = 0; $i < 2; ++$i) {
        $run();
    }
    $samples = [];
    for ($i = 0; $i < 9; ++$i) {
        gc_collect_cycles();
        $start = hrtime(true);
        $run();
        $samples[] = (hrtime(true) - $start) / 1e6;
    }
    sort($samples);
    $rows[] = ['case' => $name, 'bytes' => $bytes, 'median_ms' => $samples[4],
        'min_ms' => $samples[0], 'max_ms' => $samples[8]];
};
foreach ([1024, 16384, 65536] as $size) {
    $literal = str_repeat('a', $size);
    foreach (['exact', 'early-failure', 'late-failure', 'insensitive'] as $kind) {
        $parser = new Text($literal);
        if ($kind === 'insensitive') {
            $parser->caseInsensitive();
        }
        $input = match ($kind) {
            'early-failure' => '!' . substr($literal, 1),
            'late-failure' => substr($literal, 1) . '!',
            'insensitive' => strtoupper($literal),
            default => $literal,
        };
        $source = new Utf8Source($input);
        $expected = in_array($kind, ['exact', 'insensitive'], true);
        $measure('literal-' . $kind, $size, static function () use ($parser, $source, $expected, $kind, $size) {
            $result = $parser->parseAll($source);
            if ($result->success !== $expected) {
                throw new RuntimeException('Acceptance changed');
            }
            if ($kind === 'late-failure' && $result->farthestFailure !== $size - 1) {
                throw new RuntimeException('Failure offset changed');
            }
        });
    }
}
$size = 65536;
$source = new Utf8Source(str_repeat('a', $size));
foreach ([1, 16, 64, 256, 1024] as $chunk) {
    foreach (['text', 'regex', 'window'] as $kind) {
        $unit = match ($kind) {
            'regex' => new Regex('/a{' . $chunk . '}/A'),
            'window' => Regex::literalWindow(str_repeat('a', $chunk)),
            default => new Text(str_repeat('a', $chunk)),
        };
        $parser = Repeat::exact($unit, intdiv($size, $chunk));
        $measure('repeat-' . $kind . '-' . $chunk, $size, static function () use ($parser, $source) {
            if (!$parser->parseAll($source)->success) {
                throw new RuntimeException('Acceptance changed');
            }
        });
    }
}
foreach (['per-unit', 'bulk'] as $kind) {
    $measure('synthetic-work-' . $kind, $size, static function () use ($source, $size, $kind) {
        $session = new ParseSession($source);
        if ($kind === 'bulk') {
            $session->consumeWork($size);
        } else {
            for ($i = 0; $i < $size; ++$i) {
                $session->consumeWork(1, $i);
            }
        }
        if ($session->workUsed() !== $size) {
            throw new RuntimeException('Work count changed');
        }
    });
}
$setResults = [];
foreach ([1, 26, 256, 1024] as $width) {
    $set = '';
    for ($i = 0; $i < $width; ++$i) {
        $set .= mb_chr(0x100 + $i, 'UTF-8');
    }
    foreach (['first', 'last'] as $position) {
        $character = mb_substr($set, $position === 'first' ? 0 : $width - 1, 1, 'UTF-8');
        $source = new Utf8Source(str_repeat($character, 1024));
        $parser = Repeat::exact(new Set($set), 1024);
        $samples = [];
        for ($i = 0; $i < 11; ++$i) {
            gc_collect_cycles();
            $start = hrtime(true);
            $result = $parser->parseAll($source);
            $elapsed = (hrtime(true) - $start) / 1e6;
            if (!$result->success) {
                throw new RuntimeException('Acceptance changed');
            }
            unset($result);
            if ($i >= 2) {
                $samples[] = $elapsed;
            }
        }
        sort($samples);
        $setResults[] = ['width' => $width, 'position' => $position, 'units' => 1024,
            'median_ms' => $samples[4], 'min_ms' => $samples[0], 'max_ms' => $samples[8]];
    }
}
echo json_encode(['php' => PHP_VERSION, 'pcre' => PCRE_VERSION, 'os' => PHP_OS_FAMILY,
    'warmup_iterations' => 2, 'samples' => 9, 'source' => 'UTF-8 memory',
    'timing' => 'Parse, acceptance assertion and result destruction; source and grammar preparation excluded',
    'results' => $rows, 'set_results' => $setResults,
    'set_timing' => 'Parse only; acceptance assertion and result destruction excluded',
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
