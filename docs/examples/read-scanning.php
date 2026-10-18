<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Extraction\Extraction;

$content = Extraction::syntax()->readUntil('*/')->thenIgnore('*/');
$result = $content->parse('a comment */');
if (!$result->success || $result->value() !== 'a comment ') {
    throw new RuntimeException('Comment scanner failed');
}
$digits = C::readWhileRange('0', '9', min: 1, max: 3)->matchPrefix('1234');
if (!$digits->success || $digits->value() !== '123') {
    throw new RuntimeException('Bounded digit scanner failed');
}
$skipped = C::skipUntil(';')->matchPrefix('ignored;');
if (!$skipped->success || $skipped->value() !== null || $skipped->rawText() !== 'ignored') {
    throw new RuntimeException('Skip scanner failed');
}
echo "Read and skip scanners passed\n";
