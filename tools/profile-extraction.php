<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/profiling/MethodTrace.php';

use ComprehendProfiling\MethodTrace;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ParseMetrics;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Utf8Source;

use function ComprehendComparison\cases;
use function ComprehendComparison\comprehendJob;
use function ComprehendComparison\nodeCount;
use function ComprehendComparison\verify;

$options = getopt('', [
    'case:', 'size:', 'iterations:', 'trace', 'failure-details',
    'scan-spacing', 'scan-tokens', 'source-dir:', 'output:',
]);
$size = filter_var($options['size'] ?? 128, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$iterations = filter_var($options['iterations'] ?? 9, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($size === false || $iterations === false) {
    throw new InvalidArgumentException('Size and iterations must be positive integers');
}
$sourceDirectory = realpath($options['source-dir'] ?? __DIR__ . '/../src');
if ($sourceDirectory === false) {
    throw new InvalidArgumentException('Source directory does not exist');
}
$trace = isset($options['trace']);
$failureDetails = isset($options['failure-details']);
if ($failureDetails && !$trace) {
    throw new InvalidArgumentException('Failure details require --trace');
}
$scanSpacing = isset($options['scan-spacing']);
$scanTokens = isset($options['scan-tokens']);
$loadDirectory = $sourceDirectory;
$sourceHashes = [];
if ($trace) {
    if (!class_exists(ParserFactory::class)) {
        throw new RuntimeException('Method tracing requires nikic/php-parser from the development dependencies');
    }
    require_once __DIR__ . '/profiling/InstrumentMethods.php';
    $loadDirectory = __DIR__ . '/../build/profile-instrumented-' . getmypid();
    $phpParser = (new ParserFactory())->createForNewestSupportedVersion();
    $printer = new Standard();
    $visitor = new NodeTraverser();
    $visitor->addVisitor(new ComprehendProfiling\InstrumentMethods());
}
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceDirectory, FilesystemIterator::SKIP_DOTS),
);
foreach ($files as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $relative = substr($file->getPathname(), strlen($sourceDirectory) + 1);
    $sourceHashes[str_replace('\\', '/', $relative)] = hash_file('sha256', $file->getPathname());
    if ($trace) {
        $destination = $loadDirectory . '/' . $relative;
        if (!is_dir(dirname($destination))) {
            mkdir(dirname($destination), 0777, true);
        }
        $nodes = $phpParser->parse(file_get_contents($file->getPathname()));
        file_put_contents($destination, $printer->prettyPrintFile($visitor->traverse($nodes)));
    }
}
ksort($sourceHashes);
spl_autoload_register(static function (string $class) use ($loadDirectory): void {
    $prefix = 'Vanderlee\\Comprehend\\';
    if (str_starts_with($class, $prefix)) {
        $path = $loadDirectory . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
}, true, true);
require_once __DIR__ . '/../docs/examples/application-values-grammar.php';
require_once __DIR__ . '/extraction/grammars.php';
require_once __DIR__ . '/extraction/cases.php';
require_once __DIR__ . '/extraction/runtime.php';

$caseName = $options['case'] ?? 'nested';
$corpus = cases($size);
if (!isset($corpus[$caseName]) || $corpus[$caseName]['expected'] === null) {
    throw new InvalidArgumentException('Choose an accepted extraction case');
}
$case = $corpus[$caseName];
$grammar = comprehendJob(scanSpacing: $scanSpacing, scanTokens: $scanTokens)->freeze();
$parser = comprehendJob(scanSpacing: $scanSpacing, scanTokens: $scanTokens)->parser();
// Warm the prepared graph; preparation and correctness checks are not timed.
for ($i = 0; $i < 2; ++$i) {
    $result = $grammar->parse($case['input']);
    verify($result, $result->value(), $case);
    unset($result);
}
$observations = [];
for ($iteration = 0; $iteration < $iterations; ++$iteration) {
    gc_collect_cycles();
    $baseline = memory_get_usage(false);
    if ($trace) {
        MethodTrace::start($failureDetails);
    }
    $start = hrtime(true);
    $source = new Utf8Source($case['input']);
    $sourceNs = hrtime(true) - $start;
    $start = hrtime(true);
    $result = $grammar->parse($source);
    $recognitionNs = hrtime(true) - $start;
    $recognizedBytes = memory_get_usage(false) - $baseline;
    $recognitionTrace = $trace ? MethodTrace::stop() : null;
    if ($trace) {
        MethodTrace::start($failureDetails);
    }
    $start = hrtime(true);
    $value = $result->value();
    $projectionNs = hrtime(true) - $start;
    $projectedBytes = memory_get_usage(false) - $baseline;
    $valueTrace = $trace ? MethodTrace::stop() : null;
    $start = hrtime(true);
    $cached = $result->value();
    $cachedNs = hrtime(true) - $start;
    verify($result, $value, $case);
    if ($cached !== $value) {
        throw new RuntimeException('Cached domain value changed');
    }
    $nodes = nodeCount($result->node);
    $treeHash = hash('sha256', json_encode($result->node->toArray(), JSON_THROW_ON_ERROR));
    $start = hrtime(true);
    unset($cached, $value, $result, $source);
    $releaseNs = hrtime(true) - $start;
    $start = hrtime(true);
    $cycles = gc_collect_cycles();
    $gcNs = hrtime(true) - $start;
    $observations[] = [
        'source_ns' => $sourceNs, 'recognition_cst_ns' => $recognitionNs,
        'first_value_ns' => $projectionNs, 'cached_value_ns' => $cachedNs,
        'release_ns' => $releaseNs, 'gc_ns' => $gcNs, 'cycles' => $cycles,
        'recognized_used_delta_bytes' => $recognizedBytes, 'projected_used_delta_bytes' => $projectedBytes,
        'cst_nodes' => $nodes, 'tree_sha256' => $treeHash,
        'methods' => $recognitionTrace['methods'] ?? null,
        'source_allocations' => $recognitionTrace['allocations'] ?? null,
        'failure_allocations' => $recognitionTrace['failure_allocations'] ?? null,
        'value_methods' => $valueTrace['methods'] ?? null,
        'value_source_allocations' => $valueTrace['allocations'] ?? null,
    ];
}
// Count parser entries separately so metrics retention does not affect phase memory/timing.
$source = new Utf8Source($case['input']);
$metrics = new ParseMetrics(100000);
$session = new ParseSession($source, metrics: $metrics);
$match = $parser->recognizeInSession($source, 0, $session);
$result = new ParseResult($match);
verify($result, $result->value(), $case);
if ($result->end !== $source->byteLength()) {
    throw new RuntimeException('Instrumented recognition did not consume complete input');
}
$boundarySource = new Utf8Source("a\u{e9}\u{20ac}\u{1f642}");
$boundaryCounts = 0;
$boundaryRounds = 20000;
$start = hrtime(true);
for ($round = 0; $round < $boundaryRounds; ++$round) {
    for ($offset = 0; $offset <= 10; ++$offset) {
        $boundaryCounts += (int) $boundarySource->isUnitBoundary($offset);
        $boundaryCounts += (int) $boundarySource->isCodePointBoundary($offset);
    }
}
$boundaryNs = hrtime(true) - $start;
if ($boundaryCounts !== $boundaryRounds * 10) {
    throw new RuntimeException('Boundary probe returned inconsistent UTF-8 positions');
}
$report = [
    'schema' => 1, 'php' => PHP_VERSION, 'os' => PHP_OS_FAMILY, 'pcre' => PCRE_VERSION,
    'generated_utc' => gmdate('c'), 'profiler_sha256' => hash_file('sha256', __FILE__),
    'instrumenter_sha256' => hash_file('sha256', __DIR__ . '/profiling/InstrumentMethods.php'),
    'case' => $caseName, 'size' => $size, 'input_bytes' => strlen($case['input']), 'trace' => $trace,
    'failure_details' => $failureDetails, 'scan_spacing' => $scanSpacing, 'scan_tokens' => $scanTokens,
    'source_sha256' => hash('sha256', json_encode($sourceHashes, JSON_THROW_ON_ERROR)),
    'metrics' => $metrics->snapshot(), 'work_units' => $session->workUsed(),
    'boundary_probe' => [
        'rounds' => $boundaryRounds, 'calls' => $boundaryRounds * 22, 'elapsed_ns' => $boundaryNs,
    ],
    'trace_warning' => 'Instrumented self-times include probe overhead. '
        . 'Source new-expression counts exclude allocations '
        . 'outside instrumented methods and PHP internal allocations; they do not measure allocated bytes. '
        . 'Phase timings and retained memory are meaningful only without --trace.',
    'observations' => $observations,
];
$json = json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
if (isset($options['output'])) {
    if (file_put_contents($options['output'], $json) === false) {
        throw new RuntimeException('Could not write extraction profile');
    }
} else {
    echo $json;
}
