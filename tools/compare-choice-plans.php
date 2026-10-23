<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Builder\FrozenGrammar;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\Choice as OptimizedChoice;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ChoicePolicy;
use Vanderlee\Comprehend\Runtime\ParseMetrics;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;
use Vanderlee\Comprehend\Tools\ChoicePlans\ChoicePlanProbe;

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/choice-plans/ChoicePlanProbe.php';

$options = getopt('', ['worker', 'variant:', 'case:', 'count:', 'prefix:', 'iterations:', 'workers:', 'policy:',
    'baseline-choice:']);
$variants = ['lexical', 'dispatch', 'prefix', 'trie', 'factored', 'lazy-factored'];
$cases = ['distinct', 'shared', 'malformed', 'short', 'saturated', 'prefixes',
    'first', 'shared-first', 'second', 'empty'];
$policy = ChoicePolicy::from($options['policy'] ?? 'first');
$variant = $options['variant'] ?? 'prefix';
$baselineChoice = $options['baseline-choice'] ?? null;
$case = $options['case'] ?? 'shared';
$count = max(2, min(512, (int) ($options['count'] ?? 128)));
$prefixBytes = max(33, min(2048, (int) ($options['prefix'] ?? 512)));
$iterations = max(3, (int) ($options['iterations'] ?? 31));
if (!in_array($variant, $variants, true) || !in_array($case, $cases, true)) {
    throw new InvalidArgumentException('Unknown case or variant');
}

if (!isset($options['worker'])) {
    $workers = max(1, (int) ($options['workers'] ?? 3));
    $selectedVariants = isset($options['variant']) ? [$variant] : $variants;
    $reports = [];
    for ($worker = 0; $worker < $workers; ++$worker) {
        $order = $worker % 2 === 0 ? $selectedVariants : array_reverse($selectedVariants);
        foreach ($order as $selected) {
            $command = [PHP_BINARY, '-d', 'pcov.enabled=0', __FILE__, '--worker', '--variant=' . $selected,
                '--case=' . $case, '--count=' . $count, '--prefix=' . $prefixBytes,
                '--iterations=' . $iterations, '--policy=' . $policy->value];
            if ($baselineChoice !== null) {
                $command[] = '--baseline-choice=' . $baselineChoice;
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
    echo json_encode(['php' => PHP_VERSION, 'case' => $case, 'reports' => $reports], JSON_PRETTY_PRINT), PHP_EOL;
    exit;
}

if ($variant === 'lexical' && $baselineChoice !== null) {
    require $baselineChoice;
}

$literals = [];
for ($i = 0; $i < $count; ++$i) {
    $prefix = str_repeat('a', in_array($case, ['short', 'saturated'], true) ? 8 : $prefixBytes);
    if (in_array($case, ['distinct', 'first', 'empty'], true)) {
        $prefix = chr(33 + $i % 90) . $prefix;
    }
    $literals[] = $prefix . sprintf('%03d', $i);
}
if ($case === 'prefixes') {
    $literals[0] = substr($literals[1], 0, -3);
    if ($count > 2) {
        $literals[2] = $literals[1]; // Equal-length ties retain the earlier alternative.
    }
}
$text = $literals[$count - 1];
if ($case === 'malformed') {
    $text = substr($text, 0, -1) . '!';
} elseif (in_array($case, ['first', 'shared-first'], true)) {
    $text = $literals[0];
} elseif ($case === 'second') {
    $text = $literals[1];
} elseif ($case === 'empty') {
    $text = '';
}
$parser = new Choice(...array_map(static fn(string $text): Text => new Text($text), $literals));
$oracle = $parser->freeze(0);
// Exclude discovery, class loading and one-time optimizer startup from preparation deltas.
(new Choice(new Text('a'), new Text(str_repeat('b', 40))))->freeze()->parse('a');
gc_collect_cycles();
$before = memory_get_usage(false);
memory_reset_peak_usage();
$start = hrtime(true);
if (in_array($variant, ['trie', 'factored', 'lazy-factored'], true)) {
    $grammar = new ChoicePlanProbe($literals, $variant !== 'trie', lazy: $variant === 'lazy-factored');
    $root = $grammar;
    $preparationKind = 'prototype construction, without graph snapshot/safety analysis';
} else {
    $grammar = $parser->freeze();
    $root = (new ReflectionProperty(FrozenGrammar::class, 'root'))->getValue($grammar);
    if ($variant !== 'prefix' && property_exists($root, 'prefix')) {
        (new ReflectionProperty(OptimizedChoice::class, 'prefix'))->setValue($root, null);
    }
    if ($variant === 'lexical' && $baselineChoice === null) {
        (new ReflectionProperty(OptimizedChoice::class, 'dispatch'))->setValue($root, []);
    }
    $preparationKind = $variant === 'prefix' || $baselineChoice !== null
        ? 'complete freeze' : 'complete freeze then prefix plan discarded';
}
$prepareMs = (hrtime(true) - $start) / 1e6;
$prepareBytes = memory_get_usage(false) - $before;
$preparePeakBytes = memory_get_peak_usage(false) - $before;

// Correctness runs use separate grammars below; do not populate measured lexical entries.
$check = in_array($variant, ['trie', 'factored', 'lazy-factored'], true)
    ? new ChoicePlanProbe($literals, $variant !== 'trie', lazy: $variant === 'lazy-factored') : $parser->freeze();
$checkRoot = $check instanceof FrozenGrammar
    ? (new ReflectionProperty(FrozenGrammar::class, 'root'))->getValue($check) : $check;
if ($variant === 'lexical' && $baselineChoice === null) {
    (new ReflectionProperty(OptimizedChoice::class, 'dispatch'))->setValue($checkRoot, []);
}
if ($check instanceof FrozenGrammar && $variant !== 'prefix' && property_exists($checkRoot, 'prefix')) {
    (new ReflectionProperty(OptimizedChoice::class, 'prefix'))->setValue($checkRoot, null);
}
$checks = 0;
foreach (ChoicePolicy::cases() as $checkPolicy) {
    $checkRoot->setPreference($checkPolicy);
    $plain = (clone $parser)->setPreference($checkPolicy)->freeze(0);
    $inputs = ['', $literals[0], $literals[1], $literals[$count - 1], $text,
        substr($text, 0, -1), $text . '!', '!', "\u{e9}"];
    foreach ($inputs as $input) {
        foreach ([new ByteSource('>' . $input), new Utf8Source('>' . $input)] as $source) {
            for ($pass = 0; $pass < 2; ++$pass) {
                foreach (['matchPrefix', 'parseAll'] as $operation) {
                    $expected = ChoicePlanProbe::fingerprint($plain->$operation($source, 1));
                    if ($expected !== ChoicePlanProbe::fingerprint($check->$operation($source, 1))) {
                        throw new RuntimeException('Choice-plan differential failed');
                    }
                    ++$checks;
                }
            }
        }
    }
}
unset($check, $checkRoot, $plain);
gc_collect_cycles();
$root->setPreference($policy);
$oracle = (clone $parser)->setPreference($policy)->freeze(0);
if ($case === 'saturated') {
    for ($i = 0; $i < 160; ++$i) {
        $grammar->parseAll(new ByteSource(str_repeat('a', 8) . sprintf('%03d', $count + $i)));
    }
}
$source = new ByteSource($text);
$expected = ChoicePlanProbe::fingerprint($oracle->parseAll($source));
$beforeWarm = memory_get_usage(false);
$start = hrtime(true);
$result = $grammar->parseAll($source);
$firstMs = (hrtime(true) - $start) / 1e6;
if (ChoicePlanProbe::fingerprint($result) !== $expected) {
    throw new RuntimeException('Timed fixture differential failed');
}
unset($result);
for ($i = 0; $i < 3; ++$i) {
    $grammar->parseAll($source);
}
gc_collect_cycles();
$warmBytes = memory_get_usage(false) - $beforeWarm;
$times = [];
for ($i = 0; $i < $iterations; ++$i) {
    $start = hrtime(true);
    $result = $grammar->parseAll($source);
    $times[] = (hrtime(true) - $start) / 1e6;
    unset($result);
}
sort($times);
gc_collect_cycles();
$baseline = memory_get_usage(false);
memory_reset_peak_usage();
$result = $grammar->parseAll($source);
$retainedResultBytes = memory_get_usage(false) - $baseline;
$parsePeakBytes = memory_get_peak_usage(false) - $baseline;
unset($result);
$metrics = new ParseMetrics(100000);
$session = new ParseSession($source, metrics: $metrics);
$root->recognizeInSession($source, 0, $session);
echo json_encode(['variant' => $variant, 'policy' => $policy->value, 'count' => $count, 'input_bytes' => strlen($text),
    'lexical_control' => $baselineChoice === null ? 'current dispatch disabled' : 'saved original Choice file',
    'correctness_checks' => $checks, 'preparation_kind' => $preparationKind, 'prepare_ms' => $prepareMs,
    'prepare_used_bytes' => $prepareBytes, 'prepare_peak_used_bytes' => $preparePeakBytes, 'first_ms' => $firstMs,
    'warmed_additional_used_bytes' => $warmBytes, 'retained_result_bytes' => $retainedResultBytes,
    'parse_peak_used_bytes' => $parsePeakBytes, 'warm_median_ms' => $times[intdiv($iterations, 2)],
    'warm_min_ms' => min($times), 'warm_max_ms' => max($times),
    'plan_nodes' => $root instanceof ChoicePlanProbe ? $root->planNodes() : null,
    'work_units' => $session->workUsed()] + $metrics->snapshot(), JSON_PRETTY_PRINT), PHP_EOL;
