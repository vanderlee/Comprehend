<?php

declare(strict_types=1);

use ComprehendProfiling\InstrumentMethods;
use ComprehendProfiling\MethodTrace;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadWhileRange;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadWhileSet;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Regex;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Source\Utf8Source;

require dirname(__DIR__) . '/vendor/autoload.php';

$options = getopt('', ['worker', 'baseline:', 'case:', 'iterations:', 'workers:', 'trace']);
$case = $options['case'] ?? 'flat';
if (!in_array($case, ['flat', 'single', 'mixed', 'scanners'], true)) {
    throw new InvalidArgumentException('Unknown literal-batch case');
}
$iterations = max(3, (int) ($options['iterations'] ?? 31));
$baseline = $options['baseline'] ?? null;
$trace = isset($options['trace']);
if (!isset($options['worker'])) {
    $reports = [];
    $variants = $baseline === null ? [null] : [$baseline, null];
    for ($worker = 0; $worker < max(1, (int) ($options['workers'] ?? 3)); ++$worker) {
        foreach ($worker % 2 === 0 ? $variants : array_reverse($variants) as $directory) {
            $command = [PHP_BINARY, '-d', 'pcov.enabled=0', __FILE__, '--worker', '--case=' . $case,
                '--iterations=' . $iterations];
            if ($directory !== null) {
                $command[] = '--baseline=' . $directory;
            }
            if ($trace) {
                $command[] = '--trace';
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
    echo json_encode(['reports' => $reports], JSON_PRETTY_PRINT), PHP_EOL;
    exit;
}

$literalFile = $baseline === null ? dirname(__DIR__) . '/extensions/Optimizer/LiteralSequence.php'
    : $baseline . '/LiteralSequence.php';
$sequenceFile = $baseline === null ? dirname(__DIR__) . '/extensions/Optimizer/Parser/Sequence.php'
    : $baseline . '/Sequence.php';
require $literalFile;
require $sequenceFile;
$scannerFile = $baseline !== null && is_file($baseline . '/ScannerSequence.php')
    ? $baseline . '/ScannerSequence.php' : dirname(__DIR__) . '/extensions/Optimizer/ScannerSequence.php';
require $scannerFile;
if ($trace) {
    require __DIR__ . '/profiling/MethodTrace.php';
    require __DIR__ . '/profiling/InstrumentMethods.php';
    $parser = (new ParserFactory())->createForNewestSupportedVersion();
    $visitor = new NodeTraverser();
    $visitor->addVisitor(new InstrumentMethods());
    $source = file_get_contents(dirname(__DIR__) . '/src/Match/Success.php');
    $destination = dirname(__DIR__) . '/build/literal-success-' . getmypid() . '.php';
    file_put_contents($destination, (new Standard())->prettyPrintFile($visitor->traverse($parser->parse($source))));
    require $destination;
}

$commits = 0;
$parser = match ($case) {
    'flat' => new Sequence(...array_fill(0, 128, (new Text('a'))->name('letter'))),
    'single' => new Sequence(Repeat::exact((new Text('ab'))->name('pair'), 64)->name('block')),
    'scanners' => new Sequence(...array_fill(0, 128, new ReadWhileSet('a', 1, 1))),
    default => new Sequence(
        (new Text('!'))->onCommit(static function () use (&$commits): void {
            ++$commits;
        }),
        new Text('a'),
        new Text('b'),
        new ReadWhileSet('x', 0, 1),
        new ReadWhileRange('0', '9', 1, 3),
        new Text('c'),
        new Text('d'),
        (new Regex('/Z/A'))->setResult('marker'),
    ),
};
$text = match ($case) {
    'flat', 'scanners' => str_repeat('a', 128),
    'single' => str_repeat('ab', 64),
    default => '!abx12cdZ',
};
$parser->mapValue(static fn(ParseResult $result): array => [$result->rawText(), $result->results()]);
$source = new Utf8Source('>' . $text);
$oracle = $parser->freeze(0)->parseAll($source, 1);
$expected = [$text, $case === 'mixed' ? ['marker' => 'Z'] : []];
if (!$oracle->success || $oracle->value() !== $expected) {
    throw new RuntimeException('Ordinary fixture failed its independent value oracle');
}
$treeHash = hash('sha256', $oracle->node->toJson());
$parser->freeze()->parseAll($source, 1);
gc_collect_cycles();
$before = memory_get_usage(false);
$start = hrtime(true);
$grammar = $parser->freeze();
$prepareNs = hrtime(true) - $start;
$preparedBytes = memory_get_usage(false) - $before;
for ($i = 0; $i < 3; ++$i) {
    $grammar->parseAll($source, 1);
}
$observations = [];
for ($i = 0; $i < $iterations; ++$i) {
    gc_collect_cycles();
    $before = memory_get_usage(false);
    memory_reset_peak_usage();
    if ($trace) {
        MethodTrace::start();
    }
    $start = hrtime(true);
    $result = $grammar->parseAll($source, 1);
    $elapsed = hrtime(true) - $start;
    $retained = memory_get_usage(false) - $before;
    $peak = memory_get_peak_usage(false) - $before;
    $counts = $trace ? MethodTrace::stop() : null;
    $constructors = $counts['methods']['Vanderlee\\Comprehend\\Match\\Success::__construct']['calls'] ?? null;
    $observations[] = ['recognition_ns' => $elapsed, 'retained_bytes' => $retained, 'peak_bytes' => $peak,
        'success_constructors' => $constructors];
    if (
        !$result->success || hash('sha256', $result->node->toJson()) !== $treeHash
        || $result->diagnostic?->toArray() !== $oracle->diagnostic?->toArray()
        || $result->value() !== $expected || $result->value() !== $expected || $commits !== 0
    ) {
        throw new RuntimeException('Literal batch changed syntax, diagnostics, values or recognition purity');
    }
    unset($result);
}
$result = $grammar->parseAll($source, 1);
$result->commit();
$result->commit();
if ($commits !== ($case === 'mixed' ? 1 : 0)) {
    throw new RuntimeException('Literal batch changed explicit commit behavior');
}
echo json_encode(
    ['php' => PHP_VERSION, 'os' => PHP_OS_FAMILY, 'pcre' => PCRE_VERSION, 'case' => $case,
        'baseline' => $baseline, 'trace' => $trace, 'prepare_ns' => $prepareNs, 'prepared_bytes' => $preparedBytes,
        'literal_sha256' => hash_file('sha256', $literalFile), 'sequence_sha256' => hash_file('sha256', $sequenceFile),
        'scanner_sha256' => hash_file('sha256', $scannerFile),
        'tree_sha256' => $treeHash, 'observations' => $observations,
        'trace_warning' => 'Counts cover Success across core and extensions; traced times/memory are distorted.'],
    JSON_PRETTY_PRINT,
), PHP_EOL;
