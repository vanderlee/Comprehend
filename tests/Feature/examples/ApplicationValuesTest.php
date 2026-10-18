<?php

declare(strict_types=1);

use Examples\ApplicationValues\Job;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Source\ByteSource;

use function Examples\ApplicationValues\jobParser;

require_once __DIR__ . '/../../../docs/examples/application-values-grammar.php';

uses(TestCase::class);

test('application tutorial constructs jobs from optional alternatives and nested lists', function (
    string $input,
    string $name,
    int|bool $retries,
    array $batches,
): void {
    $parser = jobParser();
    foreach ([$parser, $parser->freeze()] as $grammar) {
        $result = $grammar->parse($input);
        $this->assertTrue($result->success, $result->diagnostic?->message() ?? 'Expected valid job');
        $job = $result->value();
        $this->assertInstanceOf(Job::class, $job);
        $this->assertSame($name, $job->name);
        $this->assertSame($retries, $job->retries);
        $this->assertSame($batches, $job->batches);
        $this->assertSame($input, $result->rawText());
        $this->assertSame($job, $result->value());
    }
})->with([
    ['report retry=off [[1, 2], [], [3]]', 'report', false, [[1, 2], [], [3]]],
    ['report [[1], [2, 3]]', 'report', 3, [[1], [2, 3]]],
    ['report retry=0 []', 'report', 0, []],
    ['report retry=9 [[]]', 'report', 9, [[]]],
    ["report\nretry=2\t[[0,\n999]]", 'report', 2, [[0, 999]]],
]);

test('application tutorial rejects malformed fields lists and trailing input', function (string $input): void {
    $result = jobParser()->freeze()->parse($input);
    $this->assertFalse($result->success);
    $this->assertNull($result->value());
    $this->assertNotNull($result->diagnostic);
})->with([
    'report retry= []', 'report retry=unknown []', 'report retry=10 []',
    'report retry=officer []', 'report [[1,]]', 'report [[1],]',
    'report [[1000]]', 'report [[1]', 'report [] extra',
    'report [[1 2]]', 'report [-1]', 'report [] ', ' report []',
]);

test('application tutorial keeps byte spans and creates independent cached domain objects', function (): void {
    $grammar = jobParser()->freeze();
    $input = 'report retry=off [[1, 2], [], [3]]';
    $result = $grammar->parse(new ByteSource($input));
    $this->assertSame(strlen($input), $result->consumedByteLength);
    $this->assertSame(0, $result->node->span->start);
    $this->assertSame(strlen($input), $result->node->span->end);
    $this->assertNotSame($result->value(), $grammar->parse($input)->value());
    $this->assertSame($input, $result->rawText());
});
