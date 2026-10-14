<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Grammar;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\Utf8Source;

final class ReleaseOneUnit extends Parser
{
    protected function recognize(Source $source, int $offset, ParseSession $session): AbstractMatch
    {
        $unit = $session->readSourceUnit($offset);

        return $unit === null ? $this->failure($source, $offset)
            : $this->success($source, $offset, $unit->byteLength());
    }

    public function __toString(): string
    {
        return 'one unit';
    }
}

$events = [];
$rules = new Ruleset();
$rules->define('number', Grammar::text('42')->named('number')
    ->mapValue(fn (ParseResult $result): int => (int) $result->rawText())->parser());
$rules->root('number')->onCommit(function (ParseResult $result) use (&$events): void {
    $events[] = $result->value();
});
$grammar = $rules->freeze();
$rules->define('number', new Text('changed'));
$result = $grammar->parseAll(new Utf8Source('42'), limits: new ParseLimits(20, 100));
if (!$result->success || $result->value() !== 42 || $events !== []
    || !$result->validate()->valid || !$result->commit() || !$result->commit() || $events !== [42]) {
    throw new RuntimeException('Canonical release lifecycle example failed');
}

$unit = (new ReleaseOneUnit())->parseAll(new Utf8Source('é'));
if (!$unit->success || $unit->consumedByteLength !== 2 || $unit->rawText() !== 'é') {
    throw new RuntimeException('Typed custom parser example failed');
}

echo "Breaking-release source, snapshot, typed extension and commit examples passed.\n";
