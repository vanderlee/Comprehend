<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Parser\Structure\Not;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\MemoizationProfile;
use Vanderlee\Comprehend\Source\ByteSource;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$probe = new Not(new Text(str_repeat('a', 64) . '!'));
$grammar = (new Sequence($probe, $probe, new Text(str_repeat('a', 64) . 'y')))->freeze();
$source = new ByteSource('>' . str_repeat('a', 64) . 'y');
$expected = $grammar->parseAll($source, 1);
$profile = new MemoizationProfile();
$result = $grammar->parseAll($source, 1, memoizationProfile: $profile);
if (
    !$result->success || $result->node?->toArray() !== $expected->node?->toArray()
    || $result->diagnostic != $expected->diagnostic
) {
    throw new RuntimeException('Profiling changed recognition');
}
$counts = $profile->snapshot();
if ($counts['strict_repeats'] !== 2 || $counts['strict_repeated_work_nonoverlap'] !== 67) {
    throw new RuntimeException('Expected repeated lookahead observations');
}
// This is observed logical work, not a cache hit count or predicted CPU saving.
echo 'Repeated contexts: ' . $counts['strict_repeats'] . '; nonoverlapping repeated work: '
    . $counts['strict_repeated_work_nonoverlap'] . PHP_EOL;
