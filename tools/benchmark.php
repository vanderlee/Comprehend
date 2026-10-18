<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Vanderlee\Comprehend\Extensions\Network\Grammar\Rfc3986;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Stub;
use Vanderlee\Comprehend\Parser\Terminal\Regex;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;

$options = getopt('', ['worker:', 'case:', 'mode:', 'source:', 'iterations:', 'size:', 'output:', 'profile']);
$iterations = max(1, (int) ($options['iterations'] ?? 5));
$size = max(64, (int) ($options['size'] ?? 4096));
$sourceType = $options['source'] ?? 'memory';
if (!in_array($sourceType, ['memory', 'memory-byte', 'file-byte', 'file-utf8'], true)) {
    throw new InvalidArgumentException('Unknown benchmark source');
}
$cases = ['text', 'text-chunked', 'unicode', 'rfc-uri', 'recursive', 'shared-prefix', 'malformed', 'large', 'regex'];
$modes = ['recognition', 'cst', 'ast', 'semantic'];
if (method_exists(Regex::class, 'literalWindow')) {
    $cases[] = 'regex-window';
}

if (isset($options['worker'])) {
    $case = $options['worker'];
    $mode = $options['mode'] ?? 'recognition';
    if (!in_array($case, $cases, true) || !in_array($mode, $modes, true)) {
        throw new InvalidArgumentException('Unknown benchmark case or mode');
    }
    $text = str_repeat('a', $size);
    $parser = Repeat::exact(new Text('a'), $size);
    $expected = true;
    if ($case === 'text-chunked') {
        $parser = Repeat::plus(new Regex('/a{1,64}/A'));
    } elseif ($case === 'unicode') {
        $text = str_repeat('é', $size);
        $parser = Repeat::exact(new Text('é'), $size);
    } elseif ($case === 'rfc-uri') {
        $text = 'https://example.com/path?q=42';
        $parser = (new Rfc3986())->ref('URI');
    } elseif ($case === 'recursive') {
        $depth = min(64, intdiv($size, 64));
        $text = str_repeat('(', $depth) . 'a' . str_repeat(')', $depth);
        $parser = new Stub();
        $parser->setParser(new Choice(new Text('a'), new Sequence(new Text('('), $parser, new Text(')'))));
    } elseif ($case === 'shared-prefix' || $case === 'malformed') {
        $prefix = new Text(str_repeat('a', $size));
        $parser = new Choice(new Sequence($prefix, new Text('x')), new Sequence($prefix, new Text('y')));
        $text .= $case === 'malformed' ? '!' : 'y';
        $expected = $case !== 'malformed';
    } elseif ($case === 'large' || $case === 'regex') {
        $text = str_repeat('a', intdiv($size, 64) * 64);
        $unit = $case === 'large' ? new Text(str_repeat('a', 64)) : new Regex('/a{64}/A');
        $parser = Repeat::exact($unit, intdiv(strlen($text), 64));
    } elseif ($case === 'regex-window') {
        $text = str_repeat('a', intdiv($size, 64) * 64);
        $parser = Repeat::exact(Regex::literalWindow(str_repeat('a', 64)), intdiv(strlen($text), 64));
    }
    $parser->name('document')->mapValue(fn($result) => $result->span->byteLength());
    $bytes = strlen($text);
    $samples = [];
    $sourceSamples = [];
    $peak = 0;
    for ($iteration = 0; $iteration < $iterations; ++$iteration) {
        gc_collect_cycles();
        if (function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }
        $sourceStart = hrtime(true);
        $path = null;
        if ($sourceType === 'memory' || $sourceType === 'memory-byte') {
            $source = $sourceType === 'memory-byte' ? new ByteSource($text) : new Utf8Source($text);
        } else {
            $path = tempnam(sys_get_temp_dir(), 'comprehend-bench-');
            file_put_contents($path, $text);
            $class = $sourceType === 'file-byte' ? \Vanderlee\Comprehend\Source\ByteFileSource::class
                : \Vanderlee\Comprehend\Source\Utf8FileSource::class;
            $source = new $class($path);
        }
        $sourceSamples[] = (hrtime(true) - $sourceStart) / 1e6;
        $start = hrtime(true);
        $profile = null;
        if (isset($options['profile'])) {
            $metrics = new \Vanderlee\Comprehend\Runtime\ParseMetrics();
            $session = new \Vanderlee\Comprehend\Runtime\ParseSession($source, metrics: $metrics);
            $match = $parser->recognizeInSession($source, 0, $session);
            $result = new \Vanderlee\Comprehend\Result\ParseResult($match);
            $profile = $metrics->snapshot() + ['work_units' => $session->workUsed()];
        } else {
            $result = $parser->parseAll($source);
        }
        if ($result->success !== $expected) {
            throw new RuntimeException('Benchmark acceptance changed: ' . $case);
        }
        if ($mode === 'cst' && $result->node !== null) {
            $output = $result->node->toArray();
        } elseif ($mode === 'ast' && $result->success) {
            $output = $result->ast();
        } elseif ($mode === 'semantic' && $result->success) {
            $output = $result->value();
        }
        $samples[] = (hrtime(true) - $start) / 1e6;
        $peak = max($peak, memory_get_peak_usage(true));
        unset($output, $result);
        if ($path !== null) {
            $source->close();
            unlink($path);
        }
        unset($source);
    }
    sort($samples);
    sort($sourceSamples);
    echo json_encode(['case' => $case, 'mode' => $mode, 'source' => $sourceType, 'input_bytes' => $bytes,
        'accepted' => $expected, 'iterations' => $iterations, 'median_ms' => $samples[intdiv($iterations, 2)],
        'min_ms' => min($samples), 'max_ms' => max($samples), 'source_median_ms' => $sourceSamples[intdiv($iterations, 2)],
        'process_peak_bytes' => $peak, 'profile' => $profile], JSON_THROW_ON_ERROR);
    exit;
}

if (isset($options['case'])) {
    if (!in_array($options['case'], $cases, true)) {
        throw new InvalidArgumentException('Unknown benchmark case');
    }
    $cases = [$options['case']];
}
if (isset($options['mode'])) {
    if (!in_array($options['mode'], $modes, true)) {
        throw new InvalidArgumentException('Unknown benchmark mode');
    }
    $modes = [$options['mode']];
}
$results = [];
foreach ($cases as $case) {
    foreach ($modes as $mode) {
        $command = [PHP_BINARY, __FILE__, '--worker=' . $case, '--mode=' . $mode,
            '--source=' . $sourceType, '--iterations=' . $iterations, '--size=' . $size];
        if (isset($options['profile'])) {
            $command[] = '--profile';
        }
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException($error);
        }
        $results[] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
}
$report = ['php' => PHP_VERSION, 'os' => PHP_OS_FAMILY, 'pcre' => PCRE_VERSION, 'size' => $size,
    'recognition_includes_cst' => true, 'file_fixture_included_in_source_time' => str_starts_with($sourceType, 'file'),
    'command' => 'php tools/benchmark.php --source=' . $sourceType . ' --iterations=' . $iterations . ' --size=' . $size
        . (isset($options['case']) ? ' --case=' . $options['case'] : '')
        . (isset($options['mode']) ? ' --mode=' . $options['mode'] : '') . (isset($options['profile']) ? ' --profile' : ''),
    'results' => $results];
$json = json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
if (isset($options['output'])) {
    file_put_contents($options['output'], $json);
} else {
    echo $json;
}
