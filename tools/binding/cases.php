<?php

declare(strict_types=1);

namespace ComprehendComparison;

/** Independent acceptance/output oracle shared by both libraries. */
function cases(int $size = 128): array
{
    $ids = array_map(static fn(int $i): int => $i % 1000, range(0, $size - 1));

    return [
        'record' => ['input' => 'report retry=off [[1, 2], [], [3]]',
            'expected' => ['report', false, [[1, 2], [], [3]]]],
        'default' => ['input' => 'report [[0,999]]', 'expected' => ['report', 3, [[0, 999]]]],
        'zero' => ['input' => 'report retry=0 []', 'expected' => ['report', 0, []]],
        'empty' => ['input' => 'report [[]]', 'expected' => ['report', 3, [[]]]],
        'nested' => ['input' => 'report retry=2 [[' . implode(',', $ids) . '],[],[3]]',
            'expected' => ['report', 2, [$ids, [], [3]]]],
        'malformed-field' => ['input' => 'report retry=unknown []', 'expected' => null],
        'missing-field-value' => ['input' => 'report retry= []', 'expected' => null],
        'retry-bound' => ['input' => 'report retry=10 []', 'expected' => null],
        'leading-zero' => ['input' => 'report [[01]]', 'expected' => null],
        'id-bound' => ['input' => 'report [[1000]]', 'expected' => null],
        'negative-zero' => ['input' => 'report [[-0]]', 'expected' => null],
        'trailing-separator' => ['input' => 'report [[1,]]', 'expected' => null],
        'outer-trailing-separator' => ['input' => 'report [[1],]', 'expected' => null],
        'missing-close' => ['input' => 'report [[1]', 'expected' => null],
        'trailing-input' => ['input' => 'report [] extra', 'expected' => null],
        'leading-space' => ['input' => ' report []', 'expected' => null],
        'trailing-space' => ['input' => 'report [] ', 'expected' => null],
        'multiline' => ['input' => "report\nretry=2\t[[1,\r\n2]]", 'expected' => ['report', 2, [[1, 2]]]],
        'multiline-error' => ['input' => "report\n[[1,\n?]]", 'expected' => null],
        'tab-error' => ['input' => "report\t[[1,?]]", 'expected' => null],
        'crlf-error' => ['input' => "report\r\n[[1,?]]", 'expected' => null],
    ];
}
