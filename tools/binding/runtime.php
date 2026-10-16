<?php

declare(strict_types=1);

namespace ComprehendComparison;

use Examples\ApplicationValues\Job;
use Parsica\Parsica\Parser as BindingParser;
use Parsica\Parsica\ParseResult as BindingResult;
use Parsica\Parsica\StringStream;
use RuntimeException;
use Vanderlee\Comprehend\Builder\FrozenGrammar;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Tree\ConcreteNode;

function recognize(FrozenGrammar|BindingParser $parser, string $input, bool $trees = true): ParseResult|BindingResult
{
    return $parser instanceof FrozenGrammar
        ? $parser->parse($input, trees: $trees)
        : $parser->run(new StringStream($input));
}

function accepted(ParseResult|BindingResult $result): bool
{
    return $result instanceof ParseResult ? $result->success : $result->isSuccess();
}

function value(ParseResult|BindingResult $result): mixed
{
    return $result instanceof ParseResult ? $result->value() : $result->output();
}

function verify(ParseResult|BindingResult $result, mixed $value, array $case): void
{
    if (accepted($result) !== ($case['expected'] !== null)) {
        throw new RuntimeException('Acceptance differs from the oracle: ' . $case['input']);
    }
    if (
        accepted($result) && (!$value instanceof Job
        || [$value->name, $value->retries, $value->batches] !== $case['expected'])
    ) {
        throw new RuntimeException('Domain output differs from the oracle: ' . $case['input']);
    }
    if ($result instanceof ParseResult && accepted($result) && $result->rawText() !== $case['input']) {
        throw new RuntimeException('Comprehend source round trip changed');
    }
}

function diagnostic(ParseResult|BindingResult $result): ?array
{
    if (accepted($result)) {
        return null;
    }
    if ($result instanceof ParseResult) {
        $diagnostic = $result->diagnostic;
        return $diagnostic === null ? null : [
            'byte_offset' => $diagnostic->byteOffset,
            ...$diagnostic->location(),
            'expected' => $diagnostic->expected,
            'message' => $diagnostic->message(),
        ];
    }
    return [
        'byte_offset' => null,
        'line' => $result->position()->line(),
        'column' => $result->position()->column(),
        'expected' => $result->expected(),
        'message' => $result->errorMessage(),
    ];
}

function nodeCount(?ConcreteNode $node): int
{
    if ($node === null) {
        return 0;
    }
    $count = 1;
    foreach ($node->children as $child) {
        $count += nodeCount($child);
    }
    return $count;
}
