<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Vanderlee\Comprehend\Builder\FreezeFlags;
use Vanderlee\Comprehend\Builder\FrozenGrammar;
use Vanderlee\Comprehend\Library\Rfc2234;
use Vanderlee\Comprehend\Library\Rfc3986;
use Vanderlee\Comprehend\Library\Rfc4291;
use Vanderlee\Comprehend\Library\Rfc5322;
use Vanderlee\Comprehend\Library\Rfc8259;
use Vanderlee\Comprehend\Library\Xml;
use Vanderlee\Comprehend\Runtime\ParseMetrics;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Utf8Source;

$options = getopt('', ['worker:', 'flags:', 'iterations:', 'samples:', 'output:']);
$iterations = max(1, (int) ($options['iterations'] ?? 30));
$samples = max(1, (int) ($options['samples'] ?? 7));
$cases = [
    'port' => [Rfc3986::class, 'port', str_repeat('1234567890', 30)],
    'abnf-string' => [Rfc2234::class, 'char_val', '"' . str_repeat('abc xyz ', 40) . '"'],
    'uri' => [Rfc3986::class, 'URI', 'https://example.com/' . str_repeat('alpha%20beta/', 25)
        . '?q=' . str_repeat('abc%2F', 20)],
    'uri-varied' => [Rfc3986::class, 'URI', 'https://example.com/' . implode('/', array_map(
        static fn(int $n): string => substr(hash('sha256', (string) $n), 0, 12),
        range(1, 25),
    ))],
    'xml-cdata' => [Xml::class, 'document', '<root><![CDATA[' . str_repeat('ordinary text ', 30) . ']]></root>'],
    'mail-list' => [Rfc5322::class, 'address_list', implode(', ', array_fill(0, 12, 'Alice <alice@example.com>'))],
    'json-array' => [Rfc8259::class, 'json_text', '[' . implode(',', array_fill(0, 30, '123')) . ']'],
    'ipv6' => [Rfc4291::class, 'ipv6_address', '2001:db8::1'],
];

if (isset($options['worker'])) {
    $case = $options['worker'];
    if (!is_string($case) || !isset($cases[$case])) {
        throw new InvalidArgumentException('Unknown Library benchmark case');
    }
    [$class, $rule, $text] = $cases[$case];
    $flags = (int) ($options['flags'] ?? FreezeFlags::DEFAULT);
    $source = new Utf8Source($text);
    $started = hrtime(true);
    $grammar = (new $class())->rule($rule)->freeze($flags);
    $prepareMs = (hrtime(true) - $started) / 1e6;
    $preparedBytes = memory_get_usage();
    $started = hrtime(true);
    $result = $grammar->parse($source);
    $coldMs = (hrtime(true) - $started) / 1e6;
    if (!$result->success || $result->consumedByteLength !== strlen($text)) {
        throw new RuntimeException('Benchmark input did not match completely');
    }
    $signature = hash('sha256', json_encode(
        [$result->success, $result->node, $result->diagnostic],
        JSON_THROW_ON_ERROR,
    ));
    unset($result);
    $times = [];
    for ($sample = 0; $sample < $samples; ++$sample) {
        $started = hrtime(true);
        for ($iteration = 0; $iteration < $iterations; ++$iteration) {
            $result = $grammar->parse($source);
            if (!$result->success || $result->consumedByteLength !== strlen($text)) {
                throw new RuntimeException('Warm recognition changed acceptance');
            }
            unset($result);
        }
        $times[] = (hrtime(true) - $started) / 1e6 / $iterations;
    }
    sort($times);
    $result = $grammar->parse($source);
    $warmSignature = hash('sha256', json_encode(
        [$result->success, $result->node, $result->diagnostic],
        JSON_THROW_ON_ERROR,
    ));
    if ($signature !== $warmSignature) {
        throw new RuntimeException('Warm recognition changed syntax or diagnostics');
    }
    unset($result);
    $retainedBytes = memory_get_usage() - $preparedBytes;
    $peak = memory_get_peak_usage(true);
    $root = (new ReflectionProperty(FrozenGrammar::class, 'root'))->getValue($grammar);
    $metrics = new ParseMetrics();
    $root->recognizeInSession($source, 0, new ParseSession($source, metrics: $metrics));
    echo json_encode([
        'case' => $case, 'flags' => $flags, 'input_bytes' => strlen($text), 'prepare_ms' => $prepareMs,
        'cold_ms' => $coldMs, 'warm_median_ms' => $times[intdiv(count($times), 2)],
        'warm_min_ms' => $times[0], 'warm_max_ms' => $times[count($times) - 1],
        'retained_after_warm_bytes' => $retainedBytes, 'process_peak_bytes' => $peak,
        'warm_parser_entries' => $metrics->snapshot()['parser_entries'], 'signature' => $signature,
    ], JSON_THROW_ON_ERROR), "\n";
    exit(0);
}

$rows = [];
foreach (array_keys($cases) as $case) {
    $modes = [];
    foreach ([FreezeFlags::NONE, FreezeFlags::OPTIMIZE] as $flags) {
        $command = [PHP_BINARY, '-d', 'memory_limit=512M', __FILE__, '--worker=' . $case,
            '--flags=' . $flags, '--iterations=' . $iterations, '--samples=' . $samples];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not launch benchmark worker');
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException('Benchmark worker failed: ' . $stderr);
        }
        $modes[$flags] = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
    }
    if ($modes[0]['signature'] !== $modes[1]['signature']) {
        throw new RuntimeException('Optimization changed syntax or diagnostics: ' . $case);
    }
    $speedup = $modes[0]['warm_median_ms'] / $modes[1]['warm_median_ms'];
    $rows[] = ['case' => $case, 'speedup' => $speedup, 'none' => $modes[0], 'optimize' => $modes[1]];
    printf(
        "%-14s %8.3f -> %8.3f ms  %5.2fx  entries %d -> %d\n",
        $case,
        $modes[0]['warm_median_ms'],
        $modes[1]['warm_median_ms'],
        $speedup,
        $modes[0]['warm_parser_entries'],
        $modes[1]['warm_parser_entries'],
    );
}
$report = ['php' => PHP_VERSION, 'os' => PHP_OS_FAMILY, 'iterations' => $iterations,
    'samples' => $samples, 'includes_cst' => true, 'results' => $rows];
$output = $options['output'] ?? __DIR__ . '/../build/library-optimizer-benchmark.json';
if (!is_string($output)) {
    throw new InvalidArgumentException('Output must be a path');
}
file_put_contents($output, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
