<?php

declare(strict_types=1);

$version = $argv[1] ?? '';
if (preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/D', $version) !== 1) {
    fwrite(STDERR, "Supply a numeric major.minor.patch release version.\n");
    exit(1);
}
if (isset($argv[2]) && $argv[2] !== '--require-date') {
    fwrite(STDERR, "Unknown option; use --require-date for publication.\n");
    exit(1);
}

$changelog = file_get_contents(__DIR__ . '/../CHANGELOG.md');
$heading = '/^## ' . preg_quote($version, '/') . ' - (?<date>[^\r\n]+)\R(?<notes>.*?)(?=^## |\z)/ms';
if (preg_match($heading, $changelog, $entry) !== 1 || trim($entry['notes']) === '') {
    fwrite(STDERR, 'Missing changelog entry for ' . $version . PHP_EOL);
    exit(1);
}

if (($argv[2] ?? '') === '--require-date') {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $entry['date']);
    if ($date === false || $date->format('Y-m-d') !== $entry['date']) {
        fwrite(STDERR, "Publication requires a dated changelog entry, not an unreleased candidate.\n");
        exit(1);
    }
}

echo trim($entry['notes']) . PHP_EOL;
