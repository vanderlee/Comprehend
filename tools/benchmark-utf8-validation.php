<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Vanderlee\Comprehend\Source\ByteFileSource;
use Vanderlee\Comprehend\Source\Utf8FileSource;

$options = getopt('', ['baseline:', 'source:', 'case:', 'size:', 'page:', 'iterations:']);
if (isset($options['baseline'])) {
    require $options['baseline'] . '/Utf8FileSource.before.php';
}
$sourceType = $options['source'] ?? 'file-utf8';
$case = $options['case'] ?? 'ascii';
if (
    !in_array($sourceType, ['file-byte', 'file-utf8'], true)
    || !in_array($case, ['ascii', 'unicode', 'malformed-start', 'malformed-end'], true)
) {
    throw new InvalidArgumentException('Unknown source or case');
}
$size = max(0, (int) ($options['size'] ?? 1048576));
$page = max(4, (int) ($options['page'] ?? 8192));
$iterations = max(1, (int) ($options['iterations'] ?? 5));
$atom = $case === 'unicode' ? "a\u{e9}\u{20ac}\u{1f642}" : 'a';
$repetitions = intdiv($size, strlen($atom));
$bytes = $repetitions * strlen($atom);
$points = $repetitions * ($case === 'unicode' ? 4 : 1);
$badPrefix = $case === 'malformed-start' ? "\xed\xa0\x80" : '';
$badSuffix = $case === 'malformed-end' ? "\xf0\x9f" : '';
$errorOffset = match ($case) {
    'malformed-start' => 1,
    'malformed-end' => $bytes + 2,
    default => null,
};
$expectedError = $sourceType === 'file-utf8' && $errorOffset !== null
    ? 'File source must contain valid UTF-8; invalid sequence at byte ' . $errorOffset : null;
$stream = tmpfile();
if ($stream === false) {
    throw new RuntimeException('Cannot create fixture');
}
$samples = [];
$peak = 0;
try {
    fwrite($stream, $badPrefix);
    $blockUnits = max(1, intdiv(8192, strlen($atom)));
    for ($remaining = $repetitions; $remaining > 0; $remaining -= $count) {
        $count = min($remaining, $blockUnits);
        fwrite($stream, str_repeat($atom, $count));
    }
    fwrite($stream, $badSuffix);
    fseek($stream, 1);
    for ($iteration = -1; $iteration < $iterations; ++$iteration) {
        gc_collect_cycles();
        memory_reset_peak_usage();
        $baseline = memory_get_usage();
        $source = null;
        $error = null;
        $start = hrtime(true);
        try {
            $source = $sourceType === 'file-byte'
                ? new ByteFileSource($stream, $page) : new Utf8FileSource($stream, $page);
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
            unset($exception);
        }
        $elapsed = (hrtime(true) - $start) / 1e6;
        $extra = memory_get_peak_usage() - $baseline;
        try {
            if ($error !== $expectedError || ftell($stream) !== 1) {
                throw new RuntimeException('UTF-8 diagnostic or borrowed stream position changed');
            }
            if (
                $source !== null && ($source->byteLength() !== $bytes + strlen($badPrefix . $badSuffix)
                || ($source instanceof Utf8FileSource && $source->codePointLength() !== $points))
            ) {
                throw new RuntimeException('UTF-8 byte length or code-point count changed');
            }
            if ($iteration >= 0) {
                $samples[] = $elapsed;
                $peak = max($peak, $extra);
            }
        } finally {
            $source?->close();
            unset($source);
        }
    }
} finally {
    fclose($stream);
}
sort($samples);
echo json_encode([
    'php' => PHP_VERSION, 'pcre' => PCRE_VERSION, 'native_mbstring' => extension_loaded('mbstring'),
    'source' => $sourceType, 'case' => $case, 'bytes' => $bytes + strlen($badPrefix . $badSuffix),
    'page' => $page, 'iterations' => $iterations, 'baseline' => $options['baseline'] ?? null,
    'median_ms' => $samples[intdiv(count($samples), 2)], 'min_ms' => min($samples), 'max_ms' => max($samples),
    'incremental_peak_used_bytes' => $peak, 'expected_code_points' => $expectedError === null ? $points : null,
    'expected_error' => $expectedError,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
