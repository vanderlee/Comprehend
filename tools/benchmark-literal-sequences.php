<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Vanderlee\Comprehend\Builder\FreezeFlags;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\Utf8Source;

$options = getopt('', ['iterations:', 'samples:', 'optimizer-dir:', 'output:']);
$iterations = max(1, (int) ($options['iterations'] ?? 20));
$samples = max(1, (int) ($options['samples'] ?? 7));
if (isset($options['optimizer-dir'])) {
    $directory = realpath($options['optimizer-dir']);
    if ($directory === false) {
        throw new InvalidArgumentException('Optimizer source directory does not exist');
    }
    spl_autoload_register(static function (string $class) use ($directory): void {
        $prefix = 'Vanderlee\\Comprehend\\Extensions\\Optimizer\\';
        if (str_starts_with($class, $prefix)) {
            $path = $directory . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($path)) {
                require $path;
            }
        }
    }, true, true);
}
$cases = [
    'flat' => new Sequence(...array_map(static fn() => new Text('a'), range(1, 512))),
    'nested' => new Sequence(...array_map(
        static fn() => new Sequence(...array_map(static fn() => new Text('a'), range(1, 32))),
        range(1, 16),
    )),
    'fixed-repeat' => new Sequence(Repeat::exact(new Text('ab'), 256), new Text('!')),
];
$rows = [];
foreach ($cases as $name => $parser) {
    $text = $name === 'fixed-repeat' ? str_repeat('ab', 256) . '!' : str_repeat('a', 512);
    $source = new Utf8Source($text);
    $grammars = [];
    $times = [[], []];
    $preparation = [];
    $signatures = [];
    foreach ([FreezeFlags::NONE, FreezeFlags::OPTIMIZE] as $flags) {
        $start = hrtime(true);
        $grammar = $parser->freeze($flags);
        $preparation[$flags] = (hrtime(true) - $start) / 1e6;
        $grammars[$flags] = $grammar;
        for ($warmup = 0; $warmup < 2; ++$warmup) {
            $result = $grammar->parse($source);
            if (!$result->success || $result->rawText() !== $text) {
                throw new RuntimeException('Literal benchmark acceptance changed');
            }
            $signatures[$flags] = hash('sha256', json_encode($result->node, JSON_THROW_ON_ERROR));
            unset($result);
        }
    }
    if ($signatures[0] !== $signatures[1]) {
        throw new RuntimeException('Literal optimization changed the CST');
    }
    // Alternate order to reduce bias from sequential host load changes.
    for ($sample = 0; $sample < $samples; ++$sample) {
        foreach ($sample % 2 === 0 ? [0, 1] : [1, 0] as $flags) {
            $start = hrtime(true);
            for ($iteration = 0; $iteration < $iterations; ++$iteration) {
                $result = $grammars[$flags]->parse($source);
                if (!$result->success || $result->end !== strlen($text)) {
                    throw new RuntimeException('Warm literal acceptance changed');
                }
                unset($result);
            }
            $times[$flags][] = (hrtime(true) - $start) / 1e6 / $iterations;
        }
    }
    $modes = [];
    foreach ($times as $flags => $values) {
        sort($values);
        $modes[$flags] = ['prepare_ms' => $preparation[$flags],
            'median_ms' => $values[intdiv($samples, 2)], 'min_ms' => min($values), 'max_ms' => max($values)];
    }
    $rows[] = ['case' => $name, 'input_bytes' => strlen($text), 'none' => $modes[0], 'optimize' => $modes[1],
        'speedup' => $modes[0]['median_ms'] / $modes[1]['median_ms'], 'cst_sha256' => $signatures[0]];
}
$report = ['php' => PHP_VERSION, 'os' => PHP_OS_FAMILY, 'iterations' => $iterations, 'samples' => $samples,
    'recognition_includes_cst' => true, 'result_release_included' => true, 'results' => $rows];
$json = json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
if (isset($options['output'])) {
    file_put_contents($options['output'], $json);
} else {
    echo $json;
}
