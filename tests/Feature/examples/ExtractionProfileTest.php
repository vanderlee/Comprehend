<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

uses(TestCase::class);

test('source instrumentation retains the same selected tree values and parser entry count', function (
    bool $scanTokens,
): void {
    $reports = [];
    foreach ([false, true] as $trace) {
        $command = [PHP_BINARY, '-d', 'memory_limit=512M', __DIR__ . '/../../../tools/profile-extraction.php',
            '--case=record', '--iterations=1', '--size=4'];
        if ($trace) {
            $command[] = '--trace';
        }
        if ($scanTokens) {
            $command[] = '--scan-tokens';
            $command[] = '--scan-spacing';
        }
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($process), $error);
        $reports[] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
    $this->assertSame($reports[0]['metrics'], $reports[1]['metrics']);
    $this->assertSame($reports[0]['work_units'], $reports[1]['work_units']);
    $this->assertSame($reports[0]['observations'][0]['tree_sha256'], $reports[1]['observations'][0]['tree_sha256']);
    $this->assertSame($reports[0]['observations'][0]['cst_nodes'], $reports[1]['observations'][0]['cst_nodes']);
    $this->assertNull($reports[0]['observations'][0]['methods']);
    $this->assertNotEmpty($reports[1]['observations'][0]['methods']);
    $allocations = $reports[1]['observations'][0]['source_allocations'];
    $this->assertGreaterThan(0, $allocations['Vanderlee\\Comprehend\\Source\\Span']);
    $this->assertGreaterThan(0, $allocations['Vanderlee\\Comprehend\\Match\\ExecutionState']);
})->with([false, true]);
