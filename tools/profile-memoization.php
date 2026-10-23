<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Directive\CaseSensitive;
use Vanderlee\Comprehend\Extensions\Abnf\Grammar\Rfc2234;
use Vanderlee\Comprehend\Extensions\Json\Grammar\Rfc8259;
use Vanderlee\Comprehend\Extensions\Network\Grammar\Rfc3986;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Not;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\MemoizationProfile;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8FileSource;
use Vanderlee\Comprehend\Source\Utf8Source;

require dirname(__DIR__) . '/vendor/autoload.php';

$options = getopt('', ['worker', 'case:', 'flags:', 'size:', 'iterations:', 'workers:', 'source:',
    'capacity:', 'context-bytes:', 'no-profile', 'baseline-runtime:']);
$cases = ['shared-prefix', 'lookahead', 'lexical', 'case-scope', 'guarded', 'uri', 'json', 'abnf'];
$sources = ['memory-byte', 'memory-utf8', 'file-utf8'];
$case = $options['case'] ?? 'shared-prefix';
$sourceKind = $options['source'] ?? 'memory-byte';
$size = max(8, min(4096, (int) ($options['size'] ?? 256)));
$iterations = max(3, (int) ($options['iterations'] ?? 31));
$capacity = max(1, (int) ($options['capacity'] ?? 16384));
$contextBytes = max(1, (int) ($options['context-bytes'] ?? 16384));
$flags = (int) ($options['flags'] ?? 1);
$noProfile = isset($options['no-profile']);
if (!in_array($case, $cases, true) || !in_array($sourceKind, $sources, true) || !in_array($flags, [0, 1], true)) {
    throw new InvalidArgumentException('Unknown profiling case, source or freeze flags');
}
if (isset($options['baseline-runtime']) && !$noProfile) {
    throw new InvalidArgumentException('An original-runtime control must use --no-profile');
}

if (!isset($options['worker'])) {
    $reports = [];
    $selectedCases = isset($options['case']) ? [$case] : $cases;
    $selectedFlags = isset($options['flags']) ? [$flags] : [0, 1];
    for ($worker = 0; $worker < max(1, (int) ($options['workers'] ?? 1)); ++$worker) {
        foreach ($selectedCases as $selectedCase) {
            foreach ($selectedFlags as $selectedFlag) {
                $command = [PHP_BINARY, '-d', 'pcov.enabled=0', __FILE__, '--worker', '--case=' . $selectedCase,
                    '--flags=' . $selectedFlag, '--source=' . $sourceKind, '--size=' . $size,
                    '--capacity=' . $capacity, '--context-bytes=' . $contextBytes, '--iterations=' . $iterations];
                if ($noProfile) {
                    $command[] = '--no-profile';
                }
                if (isset($options['baseline-runtime'])) {
                    $command[] = '--baseline-runtime=' . $options['baseline-runtime'];
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
    }
    echo json_encode(['php' => PHP_VERSION, 'os' => PHP_OS_FAMILY, 'reports' => $reports], JSON_PRETTY_PRINT), PHP_EOL;
    exit;
}

if (isset($options['baseline-runtime'])) {
    require $options['baseline-runtime'] . '/Parser.php';
    require $options['baseline-runtime'] . '/ParseSession.php';
}

$events = 0;
$text = str_repeat('a', $size) . 'y';
$prefix = new Text(str_repeat('a', $size));
$make = match ($case) {
    'shared-prefix' => static fn() => new Choice(
        new Sequence($prefix, new Text('x')),
        new Sequence($prefix, new Text('y')),
    ),
    'case-scope' => static fn() => new Choice(
        new CaseSensitive(true, new Sequence($prefix, new Text('x'))),
        new CaseSensitive(false, new Sequence($prefix, new Text('y'))),
    ),
    'lexical' => static fn() => new Choice(
        new Sequence(new Text('aaaaaaaa'), new Text('x')),
        new Sequence(new Text('aaaaaaaa'), new Text('y')),
    ),
    'uri' => static fn() => (new Rfc3986())->rule('URI'),
    'json' => static fn() => (new Rfc8259())->rule('json_text'),
    'abnf' => static fn() => (new Rfc2234())->rule('char_val'),
    default => static function () use ($case, $size, &$events): Sequence {
        $prefix = new Text(str_repeat('a', $size));
        if ($case === 'guarded') {
            $prefix->guard(static function () use (&$events): bool {
                ++$events;
                return true;
            });
        }
        $probe = new Not(new Sequence($prefix, new Text('!')));

        return new Sequence(...[...array_fill(0, 8, $probe), new Text(str_repeat('a', $size) . 'y')]);
    },
};
$text = match ($case) {
    'lexical' => 'aaaaaaaay',
    'uri' => 'https://example.com/' . str_repeat('abc%20', max(1, intdiv($size, 6))) . '?x=%2F#end',
    'json' => '[' . implode(',', array_fill(0, max(1, intdiv($size, 64)), '{"a":1,"b":[true,false,null]}')) . ']',
    'abnf' => '"' . str_repeat('a b ', max(1, intdiv($size, 4))) . '"',
    default => $text,
};
$source = match ($sourceKind) {
    'memory-byte' => new ByteSource('>' . $text),
    'memory-utf8' => new Utf8Source('>' . $text),
    default => null,
};
if ($source === null) {
    $stream = tmpfile();
    if ($stream === false) {
        throw new RuntimeException('Could not create profiling fixture');
    }
    fwrite($stream, '>' . $text);
    $source = new Utf8FileSource($stream, 64);
    fclose($stream);
}
$fingerprint = static fn(ParseResult $result): array => [$result->success, $result->consumedByteLength,
    $result->farthestFailure, $result->diagnostic?->expected, $result->diagnostic?->rulePaths,
    $result->node?->toArray(), $result->ast()?->toArray(), $result->value(), $result->results()];
$oracle = $make()->freeze($flags);
$expected = $fingerprint($oracle->parseAll($source, 1));
if (!$expected[0]) {
    throw new RuntimeException('Profiling fixture must succeed');
}
$start = hrtime(true);
$grammar = $make()->freeze($flags);
$prepareMs = (hrtime(true) - $start) / 1e6;
$phases = [];
foreach (['cold', 'warm'] as $phase) {
    if ($phase === 'warm') {
        for ($i = 0; $i < 3; ++$i) {
            $grammar->parseAll($source, 1);
        }
    }
    gc_collect_cycles();
    $before = memory_get_usage(false);
    $profile = $noProfile ? null : new MemoizationProfile($capacity, maxContextBytes: $contextBytes);
    memory_reset_peak_usage();
    $eventsBefore = $events;
    $start = hrtime(true);
    $result = $grammar->parseAll($source, 1, memoizationProfile: $profile);
    $elapsed = (hrtime(true) - $start) / 1e6;
    $peak = memory_get_peak_usage(false) - $before;
    if ($fingerprint($result) !== $expected || ($case === 'guarded' && $events - $eventsBefore !== 8)) {
        throw new RuntimeException('Instrumented recognition changed the independent fixture result');
    }
    unset($result);
    gc_collect_cycles();
    $used = memory_get_usage(false) - $before;
    $phases[$phase] = ['elapsed_ms_including_profile_registration' => $elapsed,
        'profile_and_new_grammar_cache_used_bytes' => $used, 'recognition_peak_used_bytes' => $peak,
        'profile' => $profile?->snapshot()];
    unset($profile);
}
$times = [];
for ($i = 0; $i < $iterations; ++$i) {
    $start = hrtime(true);
    $result = $grammar->parseAll($source, 1);
    $times[] = (hrtime(true) - $start) / 1e6;
    unset($result);
}
sort($times);
if ($source instanceof Utf8FileSource) {
    $source->close();
}
echo json_encode(['case' => $case, 'flags' => $flags, 'source' => $sourceKind, 'input_bytes' => strlen($text),
    'profile_enabled' => !$noProfile, 'baseline_runtime' => $options['baseline-runtime'] ?? null,
    'prepare_ms' => $prepareMs, 'unprofiled_median_ms' => $times[intdiv($iterations, 2)],
    'unprofiled_min_ms' => min($times), 'unprofiled_max_ms' => max($times),
    'phases' => $phases], JSON_PRETTY_PRINT), PHP_EOL;
