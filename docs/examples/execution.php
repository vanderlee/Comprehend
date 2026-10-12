<?php

use Vanderlee\Comprehend\Parser\Stub;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\End;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Parser\Terminal\Any;
use Vanderlee\Comprehend\Parser\Terminal\Char;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;

require_once __DIR__ . '/../../vendor/autoload.php';

$nested = new Stub();
$nested->parser = Choice::first('x', new Sequence('(', $nested, ')'));

$examples = [
    ['prefix', new Text('a'), 'ab', true, 1, 0],
    ['explicit offset', new Text('a'), 'za', true, 1, 1],
    ['trailing input', new Sequence('a', new End()), 'ab', false, 1, 0],
    ['complete input', new Sequence('a', new End()), 'a', true, 1, 0],
    ['first choice', Choice::first('a', 'ab'), 'abc', true, 1, 0],
    ['longest choice', Choice::longest('a', 'ab'), 'abc', true, 2, 0],
    ['shortest choice', Choice::shortest('ab', 'a'), 'abc', true, 1, 0],
    ['choice stays selected', new Sequence(Choice::first('a', 'ab'), 'c', new End()), 'abc', false, 1, 0],
    ['longest fits suffix', new Sequence(Choice::longest('a', 'ab'), 'c', new End()), 'abc', true, 3, 0],
    ['longest stays selected', new Sequence(Choice::longest('a', 'ab'), 'b', new End()), 'ab', false, 2, 0],
    ['first fits suffix', new Sequence(Choice::first('a', 'ab'), 'b', new End()), 'ab', true, 2, 0],
    ['factored prefix', new Sequence('a', Choice::first('bc', 'c'), new End()), 'abc', true, 3, 0],
    ['greedy repeat', new Sequence(new Repeat('a'), 'a', new End()), 'aaa', false, 3, 0],
    ['required item first', new Sequence('a', new Repeat('a'), new End()), 'aaa', true, 3, 0],
    ['consuming recursion', new Sequence($nested, new End()), '((x))', true, 5, 0],
];

foreach ($examples as [$label, $parser, $input, $accepted, $length, $offset]) {
    $result = $parser->match($input, $offset);
    if ($result->match !== $accepted || $result->length !== $length) {
        throw new RuntimeException('Documented behavior changed: ' . $label);
    }
    echo $label . ': ' . ($result->match ? 'success' : 'failure') . ', length ' . $result->length . PHP_EOL;
}

$sourceExamples = [
    ['UTF-8 wrong suffix', new Sequence(new Text("\u{00E9}a"), new End()), new Utf8Source("\u{00E9}b"), false, 2, 0],
    ['UTF-8 complete input', new Sequence(new Text("\u{00E9}a"), new End()), new Utf8Source("\u{00E9}a"), true, 3, 0],
    ['UTF-8 complete code point', new Char("\u{00E9}"), new Utf8Source("\u{00E9}"), true, 2, 0],
    ['UTF-8 supplementary code point', new Any(), new Utf8Source("\u{1F600}"), true, 4, 0],
    ['UTF-8 byte offset', new Char('a'), new Utf8Source("\u{00E9}a"), true, 1, 2],
    ['arbitrary byte', new Char(0xFF), new ByteSource("\xFF"), true, 1, 0],
];
foreach ($sourceExamples as [$label, $parser, $source, $accepted, $length, $offset]) {
    $result = $parser->matchSource($source, $offset);
    if ($result->match !== $accepted || $result->length !== $length
        || ($accepted && $result->token->text !== $source->sliceBytes($offset, $offset + $length))) {
        throw new RuntimeException('Documented source behavior changed: ' . $label);
    }
    echo $label . ': ' . ($result->match ? 'success' : 'failure') . ', bytes ' . $result->length . PHP_EOL;
}

// Report legacy match() defects without preserving them as expectations.
$encoding = mb_internal_encoding();
try {
    mb_internal_encoding('UTF-8');
    $result = (new Sequence(new Text('éa'), new End()))->match('éb');
    echo 'Legacy match() Unicode limitation probe: '
        . ($result->match ? 'accepted' : 'rejected') . PHP_EOL;
    $result = (new Text('é'))->token('letter')->match('é');
    echo 'Legacy match() Unicode token bytes: ' . bin2hex($result->token->text) . PHP_EOL;
} finally {
    mb_internal_encoding($encoding);
}
