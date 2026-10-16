<?php

declare(strict_types=1);

$options = getopt('', ['output:']);
$root = dirname(__DIR__, 2);
require_once $root . '/vendor/autoload.php';
$process = proc_open([
    PHP_BINARY, $root . '/vendor/phpstan/phpstan/phpstan', 'analyse',
    '--configuration=' . __DIR__ . '/phpstan.neon', '--memory-limit=512M',
    '--no-progress', '--error-format=json',
], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
if (!is_resource($process)) {
    throw new RuntimeException('Could not start the comparison type probe');
}
$output = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exit = proc_close($process);
$data = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
if ($exit !== 1 || ($data['errors'] ?? []) !== []) {
    throw new RuntimeException('Type probe failed before reporting observations: ' . $output . $stderr);
}
$observations = [];
foreach ($data['files'] as $path => $file) {
    foreach ($file['messages'] as $message) {
        $observations[] = [
            'file' => str_replace('\\', '/', substr($path, strlen($root) + 1)),
            'line' => $message['line'], 'identifier' => $message['identifier'],
            // Reports use the comparison label; installed package identifiers stay in Composer metadata.
            'message' => str_replace('Parsica\\Parsica\\', 'Extraction\\', $message['message']),
        ];
    }
}
$dumps = array_filter($observations, static fn(array $item): bool => $item['identifier'] === 'phpstan.dumpType');
if (count($dumps) !== 8) {
    throw new RuntimeException('Type probe did not report all eight expected observations');
}
$report = [
    'command' => 'php tools/extraction/type-report.php', 'phpstan_exit_code' => $exit,
    'phpstan_version' => Composer\InstalledVersions::getPrettyVersion('phpstan/phpstan'),
    'grammar_sha256' => hash_file('sha256', __DIR__ . '/grammars.php'),
    'probe_sha256' => hash_file('sha256', __DIR__ . '/types.php'),
    'note' => 'dumpType observations deliberately exit nonzero; other diagnostics are retained. '
        . 'Both factory output contracts are declared; primitive/list/optional expressions are inferred. '
        . 'This is a shared PHPStan probe with the Comprehend extension, not a native analyzer comparison.',
    'observations' => $observations,
];
$json = json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
if (isset($options['output'])) {
    if (file_put_contents($options['output'], $json) === false) {
        throw new RuntimeException('Could not write type observations');
    }
} else {
    echo $json;
}
