<?php

declare(strict_types=1);

// Positive assertType fixtures run in the normal PHPStan configuration. This separate
// invocation proves incompatible callbacks are rejected, without ignoring diagnostics.
$root = dirname(__DIR__);
$process = proc_open([
    PHP_BINARY,
    $root . '/vendor/phpstan/phpstan/phpstan',
    'analyse',
    '--configuration=' . $root . '/phpstan.neon',
    '--memory-limit=512M',
    '--no-progress',
    '--error-format=json',
    $root . '/tests/Fixtures/PHPStan/invalid-values.php',
], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
if (!is_resource($process)) {
    throw new RuntimeException('Could not start PHPStan for invalid value callbacks');
}
$output = stream_get_contents($pipes[1]);
$errors = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exit = proc_close($process);
$report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
$messages = [];
foreach ($report['files'] ?? [] as $file) {
    array_push($messages, ...$file['messages']);
}
if ($exit !== 1 || ($report['errors'] ?? []) !== [] || count($messages) !== 4) {
    throw new RuntimeException('Expected four incompatible callback diagnostics: ' . $output . $errors);
}
foreach ($messages as $message) {
    if (($message['identifier'] ?? null) !== 'argument.type') {
        throw new RuntimeException('Unexpected diagnostic: ' . $message['message']);
    }
}
echo "Incompatible value callbacks rejected\n";
