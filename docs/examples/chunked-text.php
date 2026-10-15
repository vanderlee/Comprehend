<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Terminal\Regex;
use Vanderlee\Comprehend\Source\Utf8Source;

// An application that needs a whole payload, rather than a node per character,
// can choose bounded lexical runs. The final run may contain fewer than 64 units.
$parser = Repeat::plus(new Regex('/a{1,64}/A'))->name('payload');
foreach ([1, 63, 64, 65, 65537] as $length) {
    $input = str_repeat('a', $length);
    $result = $parser->parseAll(new Utf8Source($input));
    if (
        !$result->success || $result->rawText() !== $input
        || $result->ast()?->rawText() !== $input
        || count($result->node->children) !== (int) ceil($length / 64)
    ) {
        throw new RuntimeException('Chunked text round trip failed');
    }
}
foreach (['', 'aaa!', '!aaa'] as $input) {
    if ($parser->parseAll(new Utf8Source($input))->success) {
        throw new RuntimeException('Chunked text accepted malformed input');
    }
}
echo "Chunked payload recognition, tree size and source round trips passed.\n";
