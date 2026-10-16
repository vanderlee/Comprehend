<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use function ComprehendComparison\accepted;
use function ComprehendComparison\cases;
use function ComprehendComparison\comprehendJob;
use function ComprehendComparison\diagnostic;
use function ComprehendComparison\bindingJob;
use function ComprehendComparison\recognize;
use function ComprehendComparison\value;
use function ComprehendComparison\verify;

require_once __DIR__ . '/../../../docs/examples/application-values-grammar.php';
require_once __DIR__ . '/../../../tools/binding/grammars.php';
require_once __DIR__ . '/../../../tools/binding/cases.php';
require_once __DIR__ . '/../../../tools/binding/runtime.php';

uses(TestCase::class);

test('comparison grammars satisfy the independent acceptance and output oracle', function (string $library): void {
    if ($library === 'binding') {
        $autoload = __DIR__ . '/../../../build/binding/vendor/autoload.php';
        if (!is_file($autoload)) {
            $this->markTestSkipped('Optional Binding comparison dependency is not installed');
        }
        require_once $autoload;
    }
    $parser = $library === 'comprehend' ? comprehendJob()->freeze() : bindingJob();
    foreach (cases(4) as $case) {
        $result = recognize($parser, $case['input']);
        $output = accepted($result) ? value($result) : null;
        verify($result, $output, $case);
        $this->assertSame($case['expected'] !== null, accepted($result), $case['input']);
        if (!accepted($result)) {
            $failure = diagnostic($result);
            $this->assertNotNull($failure);
            $this->assertGreaterThanOrEqual(1, $failure['line']);
            $this->assertGreaterThanOrEqual(1, $failure['column']);
            $this->assertNotEmpty($failure['message']);
        }
    }
})->with(['comprehend', 'binding']);

test('comparison oracle detects wrong acceptance and wrong domain values', function (bool $wrongAcceptance): void {
    $parser = comprehendJob()->freeze();
    $result = $parser->parse('report []');
    $this->expectException(RuntimeException::class);
    verify($result, $result->value(), ['input' => 'report []',
        'expected' => $wrongAcceptance ? null : ['report', 9, []]]);
})->with([false, true]);
