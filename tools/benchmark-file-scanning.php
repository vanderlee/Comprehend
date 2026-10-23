<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadUntil;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadWhileRange;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadWhileSet;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteFileSource;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8FileSource;
use Vanderlee\Comprehend\Source\Utf8Source;

$options = getopt('', ['source:', 'case:', 'size:', 'page:', 'iterations:', 'mode:', 'unicode', 'delimiter-bytes:']);
$sourceType = $options['source'] ?? 'file-utf8';
$case = $options['case'] ?? 'until';
$mode = $options['mode'] ?? 'chunked';
if (
    !in_array($sourceType, ['file-byte', 'file-utf8'], true)
    || !in_array($case, ['until', 'set', 'range'], true)
    || !in_array($mode, ['chunked', 'scalar'], true)
) {
    throw new InvalidArgumentException('Unknown source, case or mode');
}
$size = max(1, (int) ($options['size'] ?? 262144));
$page = max(4, (int) ($options['page'] ?? 8192));
$iterations = max(1, (int) ($options['iterations'] ?? 5));
$delimiterBytes = max(1, (int) ($options['delimiter-bytes'] ?? 1));
$atom = isset($options['unicode']) ? "a\u{e9}\u{20ac}\u{1f642}" : 'a';
$text = str_repeat($atom, max(1, intdiv($size, strlen($atom))));
$parser = match ($case) {
    'until' => new ReadUntil(str_repeat('a', $delimiterBytes - 1) . '!'),
    'set' => new ReadWhileSet($atom),
    'range' => new ReadWhileRange("\0", null),
};
$memory = $sourceType === 'file-byte' ? new ByteSource($text) : new Utf8Source($text);
$referenceSession = new ParseSession($memory, limits: new ParseLimits(maxWork: PHP_INT_MAX));
$reference = $parser->recognizeInSession($memory, 0, $referenceSession);
$stream = tmpfile();
if ($stream === false) {
    throw new RuntimeException('Cannot create fixture');
}
try {
    fwrite($stream, $text);
    $start = hrtime(true);
    $source = $sourceType === 'file-byte' ? new ByteFileSource($stream, $page) : new Utf8FileSource($stream, $page);
    $setupMs = (hrtime(true) - $start) / 1e6;
} finally {
    fclose($stream);
}
$limits = new ParseLimits(maxWork: $mode === 'scalar' ? PHP_INT_MAX : null);
$samples = [];
$peak = 0;
try {
    for ($iteration = -1; $iteration < $iterations; ++$iteration) {
        gc_collect_cycles();
        memory_reset_peak_usage();
        $baseline = memory_get_usage();
        $start = hrtime(true);
        $session = new ParseSession($source, limits: $limits);
        $match = $parser->recognizeInSession($source, 0, $session);
        $elapsed = (hrtime(true) - $start) / 1e6;
        $extra = memory_get_peak_usage() - $baseline;
        if (
            [$match->accepted, $match->length, $session->workUsed()]
            !== [$reference->accepted, $reference->length, $referenceSession->workUsed()]
            || $match->node?->toArray() !== $reference->node?->toArray()
        ) {
            throw new RuntimeException('Scalar differential check failed');
        }
        if ($iteration >= 0) {
            $samples[] = $elapsed;
            $peak = max($peak, $extra);
        }
        unset($match, $session);
    }
} finally {
    $source->close();
}
sort($samples);
echo json_encode([
    'php' => PHP_VERSION, 'pcre' => PCRE_VERSION, 'source' => $sourceType, 'case' => $case,
    'mode' => $mode, 'bytes' => strlen($text), 'page' => $page, 'delimiter_bytes' => $delimiterBytes,
    'source_setup_ms' => $setupMs, 'iterations' => $iterations,
    'median_ms' => $samples[intdiv(count($samples), 2)], 'min_ms' => min($samples), 'max_ms' => max($samples),
    'incremental_peak_used_bytes' => $peak, 'work' => $referenceSession->workUsed(),
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
