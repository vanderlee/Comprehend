<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Vanderlee\Comprehend\Parser\Terminal\Regex;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteFileSource;
use Vanderlee\Comprehend\Source\Utf8FileSource;

$options = getopt('', ['baseline:', 'source:', 'case:', 'size:', 'page:', 'iterations:', 'repeats:', 'unicode']);
// Saved classes let fresh workers compare implementations without changing the checkout.
if (isset($options['baseline'])) {
    foreach (['ByteFileSource', 'Utf8FileSource', 'ParseSession'] as $class) {
        require $options['baseline'] . '/' . $class . '.before.php';
    }
}
$sourceType = $options['source'] ?? 'file-utf8';
$case = $options['case'] ?? 'units';
if (
    !in_array($sourceType, ['file-byte', 'file-utf8'], true)
    || !in_array($case, ['units', 'subject', 'regex'], true)
) {
    throw new InvalidArgumentException('Unknown source or case');
}
$size = max(1, (int) ($options['size'] ?? 65536));
$page = max(4, (int) ($options['page'] ?? 8192));
$iterations = max(1, (int) ($options['iterations'] ?? 5));
$repeats = max(1, (int) ($options['repeats'] ?? 1000));
$atom = isset($options['unicode']) ? "a\u{e9}\u{20ac}\u{1f642}" : 'a';
$text = str_repeat($atom, max(1, intdiv($size, strlen($atom))));
$bytes = strlen($text);
$unitCount = $sourceType === 'file-byte' ? $bytes : mb_strlen($text, 'UTF-8');
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
$session = new ParseSession($source, limits: new ParseLimits(maxRegexSubjectBytes: $bytes));
$parser = new Regex('/a/A');
$offset = $bytes - strlen($atom);
$samples = [];
$peak = 0;
try {
    // Materialization is measured separately from warm subject/regex access.
    $start = hrtime(true);
    if ($session->regexSubject() !== $text) {
        throw new RuntimeException('Materialized subject changed');
    }
    $subjectMs = (hrtime(true) - $start) / 1e6;
    $expectedWork = null;
    for ($iteration = -1; $iteration < $iterations; ++$iteration) {
        memory_reset_peak_usage();
        $baseline = memory_get_usage();
        $work = $session->workUsed();
        $checksum = 0;
        $count = 0;
        $start = hrtime(true);
        if ($case === 'units') {
            for ($position = 0; ($unit = $source->readUnit($position)) !== null; $position = $unit->end) {
                $checksum += strlen($unit->rawText());
                ++$count;
            }
        } else {
            for ($i = 0; $i < $repeats; ++$i) {
                if ($case === 'subject') {
                    if (!$source->isUnitBoundary($offset)) {
                        throw new RuntimeException('Expected unit boundary');
                    }
                    $checksum += strlen($session->regexSubject());
                } else {
                    $match = $parser->recognizeInSession($source, $offset, $session);
                    if (!$match->accepted || $match->length !== 1) {
                        throw new RuntimeException('Regex recognition changed');
                    }
                    $checksum += $match->length;
                    unset($match);
                }
            }
        }
        $elapsed = (hrtime(true) - $start) / 1e6;
        $extra = memory_get_peak_usage() - $baseline;
        $used = $session->workUsed() - $work;
        $expectedChecksum = match ($case) {
            'units' => $bytes,
            'subject' => $bytes * $repeats,
            'regex' => $repeats,
        };
        if (
            $checksum !== $expectedChecksum || ($case === 'units' && $count !== $unitCount)
            || ($expectedWork !== null && $used !== $expectedWork)
        ) {
            throw new RuntimeException('Source access or logical work changed');
        }
        $expectedWork = $used;
        if ($iteration >= 0) {
            $samples[] = $elapsed;
            $peak = max($peak, $extra);
        }
    }
} finally {
    $source->close();
}
sort($samples);
echo json_encode([
    'php' => PHP_VERSION, 'pcre' => PCRE_VERSION, 'source' => $sourceType, 'case' => $case,
    'bytes' => $bytes, 'page' => $page, 'repeats' => $repeats, 'iterations' => $iterations,
    'unicode' => isset($options['unicode']),
    'baseline' => $options['baseline'] ?? null, 'source_setup_ms' => $setupMs,
    'first_subject_ms' => $subjectMs, 'median_ms' => $samples[intdiv(count($samples), 2)],
    'min_ms' => min($samples), 'max_ms' => max($samples),
    'incremental_peak_used_bytes' => $peak, 'work_per_observation' => $expectedWork,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
