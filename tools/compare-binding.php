<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../docs/examples/application-values-grammar.php';
require_once __DIR__ . '/binding/grammars.php';
require_once __DIR__ . '/binding/cases.php';
require_once __DIR__ . '/binding/runtime.php';

use Composer\InstalledVersions;
use Vanderlee\Comprehend\Result\ParseResult;

use function ComprehendComparison\accepted;
use function ComprehendComparison\cases;
use function ComprehendComparison\comprehendJob;
use function ComprehendComparison\diagnostic;
use function ComprehendComparison\nodeCount;
use function ComprehendComparison\bindingJob;
use function ComprehendComparison\recognize;
use function ComprehendComparison\value;
use function ComprehendComparison\verify;

mb_internal_encoding('UTF-8');

$options = getopt('', [
    'worker:', 'verify-only', 'case:', 'samples:', 'iterations:', 'warmups:', 'size:', 'output:', 'scanners',
]);
$samples = filter_var($options['samples'] ?? 3, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 50],
]);
$iterations = filter_var($options['iterations'] ?? 10, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 10000],
]);
$warmups = filter_var($options['warmups'] ?? 2, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 0, 'max_range' => 10000],
]);
$size = filter_var($options['size'] ?? 128, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 10000],
]);
if ($samples === false || $iterations === false || $warmups === false || $size === false) {
    throw new InvalidArgumentException('Invalid samples, iterations, warmups or size');
}
$corpus = cases($size);
$libraries = isset($options['scanners'])
    ? ['comprehend', 'comprehend-spacing', 'comprehend-scanners', 'binding']
    : ['comprehend', 'binding'];
$benchmarkCases = ['record', 'default', 'nested', 'malformed-field', 'multiline-error', 'trailing-input'];
if (isset($options['case'])) {
    if (!is_string($options['case']) || !isset($corpus[$options['case']])) {
        throw new InvalidArgumentException('Unknown case');
    }
    $benchmarkCases = [$options['case']];
}
$bindingAutoload = __DIR__ . '/../build/binding/vendor/autoload.php';
if (!is_file($bindingAutoload)) {
    throw new RuntimeException(
        'Install the optional dependency: composer install --working-dir=tools/binding --no-scripts',
    );
}
if (!isset($options['worker']) || $options['worker'] === 'binding') {
    require_once $bindingAutoload;
    if (InstalledVersions::getPrettyVersion('parsica-php/parsica') !== '0.8.3') {
        throw new RuntimeException('This comparison requires the pinned Binding 0.8.3');
    }
}

if (isset($options['worker'])) {
    $library = $options['worker'];
    if (!in_array($library, $libraries, true)) {
        throw new InvalidArgumentException('Unknown worker library');
    }
    $prepareStart = hrtime(true);
    $parser = $library === 'binding' ? bindingJob() : comprehendJob(
        scanSpacing: $library !== 'comprehend',
        scanTokens: $library === 'comprehend-scanners',
    )->freeze();
    $prepareMs = (hrtime(true) - $prepareStart) / 1e6;
    $observations = [];
    foreach ($corpus as $name => $case) {
        $result = recognize($parser, $case['input']);
        $value = accepted($result) ? value($result) : null;
        verify($result, $value, $case);
        $observations[$name] = ['accepted' => accepted($result), 'diagnostic' => diagnostic($result)];
        unset($result, $value);
    }
    if (isset($options['verify-only'])) {
        echo json_encode(['library' => $library, 'cases' => $observations], JSON_THROW_ON_ERROR);
        exit;
    }
    $name = $benchmarkCases[0];
    $case = $corpus[$name];
    for ($i = 0; $i < $warmups; ++$i) {
        $result = recognize($parser, $case['input']);
        $value = accepted($result) ? value($result) : null;
        verify($result, $value, $case);
        unset($result, $value);
    }
    $times = array_fill(0, $iterations, 0.0);
    gc_collect_cycles();
    $baselineUsed = memory_get_usage(false);
    $baselineAllocated = memory_get_usage(true);
    $peakReset = function_exists('memory_reset_peak_usage');
    if ($peakReset) {
        memory_reset_peak_usage();
    }
    for ($i = 0; $i < $iterations; ++$i) {
        $start = hrtime(true);
        $result = recognize($parser, $case['input']);
        $value = accepted($result) ? value($result) : null;
        $times[$i] = (hrtime(true) - $start) / 1e3;
        verify($result, $value, $case);
        if ($i + 1 < $iterations) {
            unset($result, $value);
            gc_collect_cycles();
        }
    }
    gc_collect_cycles();
    $retained = memory_get_usage(false) - $baselineUsed;
    $peakUsed = memory_get_peak_usage(false);
    $peakAllocated = memory_get_peak_usage(true);
    $nodes = $result instanceof ParseResult ? nodeCount($result->node) : null;
    $orderedTimes = $times;
    sort($orderedTimes);
    echo json_encode([
        'library' => $library, 'case' => $name, 'input_bytes' => strlen($case['input']),
        'pcov_enabled' => (bool) ini_get('pcov.enabled'),
        'prepare_ms' => $prepareMs, 'iterations' => $iterations, 'warmups' => $warmups,
        'median_us' => ($orderedTimes[intdiv($iterations - 1, 2)] + $orderedTimes[intdiv($iterations, 2)]) / 2,
        'min_us' => min($times), 'max_us' => max($times),
        'times_us' => $times, 'baseline_used_bytes' => $baselineUsed,
        'baseline_allocated_bytes' => $baselineAllocated, 'retained_used_delta_bytes' => $retained,
        'peak_used_bytes' => $peakUsed, 'peak_allocated_bytes' => $peakAllocated,
        'peak_reset_after_warmup' => $peakReset, 'retained_cst_nodes' => $nodes,
    ], JSON_THROW_ON_ERROR);
    exit;
}

$run = static function (array $arguments) use ($options): array {
    if (isset($options['scanners'])) {
        $arguments[] = '--scanners';
    }
    $command = [PHP_BINARY, '-d', 'memory_limit=512M', '-d', 'pcov.enabled=0', __FILE__, ...$arguments];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start comparison worker');
    }
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Comparison worker failed: ' . $error . $output);
    }
    return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
};
$correctness = [];
foreach ($libraries as $library) {
    $correctness[$library] = $run(['--worker=' . $library, '--verify-only', '--size=' . $size]);
}
$measurements = [];
if (!isset($options['verify-only'])) {
    for ($sample = 0; $sample < $samples; ++$sample) {
        foreach ($benchmarkCases as $case) {
            // Rotate starting positions; reverse alternate cycles to balance ordering effects.
            $start = $sample % count($libraries);
            $order = [...array_slice($libraries, $start), ...array_slice($libraries, 0, $start)];
            if (intdiv($sample, count($libraries)) % 2 === 1) {
                $order = array_reverse($order);
            }
            foreach ($order as $library) {
                $measurement = $run(['--worker=' . $library, '--case=' . $case, '--size=' . $size,
                    '--iterations=' . $iterations, '--warmups=' . $warmups]);
                $measurements[] = ['sample' => $sample, ...$measurement];
            }
        }
    }
}
$sourceHashes = [];
$runtimeDirectory = __DIR__ . '/../src';
$runtimeFiles = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($runtimeDirectory, FilesystemIterator::SKIP_DOTS),
);
foreach ($runtimeFiles as $file) {
    if ($file->getExtension() === 'php') {
        $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen($runtimeDirectory)));
        $sourceHashes[$relativePath] = hash_file('sha256', $file->getPathname());
    }
}
ksort($sourceHashes);
$report = [
    'schema' => 1, 'generated_utc' => gmdate('c'), 'php' => PHP_VERSION, 'os' => PHP_OS_FAMILY,
    'pcre' => PCRE_VERSION, 'mbstring_native' => extension_loaded('mbstring'),
    'mb_internal_encoding' => mb_internal_encoding(),
    'binding_version' => InstalledVersions::getPrettyVersion('parsica-php/parsica'),
    'binding_reference' => InstalledVersions::getReference('parsica-php/parsica'),
    'runtime_source_sha256' => hash('sha256', json_encode($sourceHashes, JSON_THROW_ON_ERROR)),
    'grammar_sha256' => hash_file('sha256', __DIR__ . '/binding/grammars.php'),
    'harness_sha256' => hash_file('sha256', __FILE__),
    'case_sha256' => hash_file('sha256', __DIR__ . '/binding/cases.php'),
    'command' => 'php tools/compare-binding.php --samples=' . $samples . ' --iterations=' . $iterations
        . ' --warmups=' . $warmups . ' --size=' . $size
        . (isset($options['case']) ? ' --case=' . $options['case'] : '')
        . (isset($options['scanners']) ? ' --scanners' : '')
        . (isset($options['verify-only']) ? ' --verify-only' : ''),
    'samples' => $samples, 'iterations' => $iterations, 'warmups' => $warmups, 'size' => $size,
    'comprehend_includes_cst' => true,
    'variants' => $libraries,
    'worker_php_options' => ['memory_limit=512M', 'pcov.enabled=0'],
    'sample_order' => 'Rotate starting positions each sample; reverse alternate complete cycles',
    'timing' => 'Fresh UTF-8 source/stream, full recognition and domain value; '
        . 'excludes preparation, assertions, diagnostics formatting, result release and cycle collection',
    'memory' => 'Same warmed worker; retained result and domain object; peaks include baseline process allocations. '
        . 'Peak reset is unavailable on PHP 8.1.',
    'corpus' => $corpus, 'correctness' => $correctness, 'measurements' => $measurements,
];
$json = json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
if (isset($options['output'])) {
    if (file_put_contents($options['output'], $json) === false) {
        throw new RuntimeException('Could not write comparison report');
    }
} else {
    echo $json;
}
