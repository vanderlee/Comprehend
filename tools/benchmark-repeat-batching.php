<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Parser\Terminal\Any;
use Vanderlee\Comprehend\Source\Utf8Source;
use Vanderlee\Comprehend\Source\Utf8FileSource;

require dirname(__DIR__) . '/vendor/autoload.php';

$options = getopt('', ['worker', 'baseline:', 'iterations:', 'count:', 'source:', 'literal:', 'any']);
$iterations = max(3, (int) ($options['iterations'] ?? 15));
$count = max(1, (int) ($options['count'] ?? 32768));
$literal = $options['literal'] ?? 'ab';
if ($literal === '') {
    throw new InvalidArgumentException('Literal must not be empty');
}
$sourceKind = $options['source'] ?? 'memory';
if (!in_array($sourceKind, ['memory', 'file'], true)) {
    throw new InvalidArgumentException('Source must be memory or file');
}
if (!isset($options['worker'])) {
    $reports = [];
    for ($worker = 0; $worker < 3; ++$worker) {
        $variants = isset($options['baseline']) ? [$options['baseline'], null] : [null];
        foreach ($worker % 2 === 0 ? $variants : array_reverse($variants) as $baseline) {
            $command = [PHP_BINARY, '-d', 'pcov.enabled=0', __FILE__, '--worker',
                '--iterations=' . $iterations, '--count=' . $count, '--source=' . $sourceKind,
                '--literal=' . $literal];
            if (isset($options['any'])) {
                $command[] = '--any';
            }
            if ($baseline !== null) {
                $command[] = '--baseline=' . $baseline;
            }
            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            if (proc_close($process) !== 0) {
                throw new RuntimeException($error . $output);
            }
            $reports[] = ['worker' => $worker] + json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        }
    }
    echo json_encode(['reports' => $reports], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
    exit;
}
if (isset($options['baseline'])) {
    require $options['baseline'];
}
$text = str_repeat($literal, $count);
$stream = null;
if ($sourceKind === 'file') {
    $stream = tmpfile();
    fwrite($stream, $text);
    $source = new Utf8FileSource($stream);
} else {
    $source = new Utf8Source($text);
}
$parser = isset($options['any'])
    ? Repeat::plus(new Any())
    : Repeat::exact(new Text($literal), $count);
$grammar = $parser->freeze();
$oracle = $parser->freeze(0)->parse($source);
$expected = hash('sha256', json_encode($oracle->node->toArray(), JSON_THROW_ON_ERROR));
unset($oracle);
$samples = [];
$retained = [];
for ($iteration = -2; $iteration < $iterations; ++$iteration) {
    gc_collect_cycles();
    $before = memory_get_usage(false);
    $start = hrtime(true);
    $result = $grammar->parse($source);
    $elapsed = (hrtime(true) - $start) / 1e6;
    $delta = memory_get_usage(false) - $before;
    if (
        !$result->success || $result->value() !== $text
        || hash('sha256', json_encode($result->node->toArray(), JSON_THROW_ON_ERROR)) !== $expected
    ) {
        throw new RuntimeException('Batched repetition changed output');
    }
    if ($iteration >= 0) {
        $samples[] = $elapsed;
        $retained[] = $delta;
    }
    unset($result);
}
sort($samples);
sort($retained);
echo json_encode(['php' => PHP_VERSION, 'source' => $sourceKind, 'baseline' => $options['baseline'] ?? null,
    'literal_hex' => bin2hex($literal),
    'operand' => isset($options['any']) ? 'any' : 'text',
    'input_bytes' => strlen($text), 'iterations' => $iterations, 'median_ms' => $samples[intdiv($iterations, 2)],
    'min_ms' => min($samples), 'max_ms' => max($samples),
    'retained_used_bytes' => $retained[intdiv($iterations, 2)]], JSON_THROW_ON_ERROR), PHP_EOL;
if ($stream !== null) {
    $source->close();
    fclose($stream);
}
