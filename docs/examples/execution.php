<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Library\Rfc2234;
use Vanderlee\Comprehend\Library\Rfc3986;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Stub;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Any;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\Utf8Source;

require_once __DIR__ . '/../../vendor/autoload.php';

function verify(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException('Example failed: ' . $label);
    }
}

$nested = new Stub();
$nested->setParser(Choice::first(new Text('x'), new Sequence(new Text('('), $nested, new Text(')'))));
$cases = [
    ['first choice', Choice::first(new Text('a'), new Text('ab')), 'abc', true, 1],
    ['longest choice', Choice::longest(new Text('a'), new Text('ab')), 'abc', true, 2],
    ['shortest choice', Choice::shortest(new Text('ab'), new Text('a')), 'abc', true, 1],
    ['choice stays selected', new Sequence(Choice::first(new Text('a'), new Text('ab')), new Text('c')), 'abc', false, 0],
    ['factored prefix', new Sequence(new Text('a'), Choice::first(new Text('bc'), new Text('c'))), 'abc', true, 3],
    ['greedy repeat stays greedy', new Sequence(Repeat::star(new Text('a')), new Text('a')), 'aaa', false, 0],
    ['consuming recursion', $nested, '((x))', true, 5],
    ['Unicode wrong suffix', new Text('éa'), 'éb', false, 0],
    ['whole code point', new Any(), '😀a', true, 4],
];
foreach ($cases as [$label, $parser, $input, $accepted, $bytes]) {
    $result = $parser->matchPrefix(new Utf8Source($input));
    verify($result->success === $accepted && $result->consumedByteLength === $bytes, $label);
    echo $label . ': ' . ($accepted ? 'accepted' : 'rejected') . PHP_EOL;
}
$literal = new Text('a');
verify($literal->matchPrefix(new Utf8Source('ab'))->success, 'prefix policy');
verify(!$literal->parseAll(new Utf8Source('ab'))->success, 'complete-input policy');
verify($literal->parseAll(new Utf8Source('éa'), 2)->success, 'explicit byte offset');
$failure = (new Text('éa'))->parseAll(new Utf8Source('éb'));
verify($failure->start === $failure->end && $failure->farthestFailure === 2, 'failure span');
verify($failure->diagnostic->location() === ['line' => 1, 'column' => 2], 'Unicode diagnostic');

// Explicit trivia boundaries and independent semantic/source projections.
$trivia = C::text(' ')->star()->trivia();
$letter = C::text('é')->named('letter')->setResult('letter', 'changed');
$body = C::text('(')->then($letter, C::text(')'))->spacing($trivia);
$document = C::sequence($trivia, $body, $trivia)->spacing(null)->freeze();
$result = $document->parseAll(new Utf8Source(' ( é ) '));
verify($result->success && $result->rawText() === ' ( é ) ', 'trivia source round trip');
verify($result->ast()->children[0]->rawText() === 'é', 'named AST source');
verify($result->result('letter') === 'changed', 'separate semantic collection');
$json = json_decode($result->node->toJson(), true, flags: JSON_THROW_ON_ERROR);
verify($json['text'] === $result->rawText(), 'CST JSON');
$xml = $result->node->toXml();
verify(base64_decode($xml->getElementsByTagName('text')[0]->textContent) === $result->rawText(), 'CST XML');
$raw = "\xFF\0";
$binary = (new Text($raw))->parseAll(new ByteSource($raw))->node->toArray();
verify($binary['textEncoding'] === 'base64' && base64_decode($binary['text']) === $raw, 'binary serialization');

// Named definitions and reusable RFC imports use the same source runtime.
$rules = new Ruleset([], 'example');
$rules->define('twice', static fn(C $item): C => $item->repeat(2, 2));
$rules->root($rules->rule('twice', C::text('x')));
verify($rules->parseAll(new ByteSource('xx'))->success, 'parameterized named grammar');
verify(C::text('a')->then(C::text('b'))->freeze()->parseAll(new ByteSource('ab'))->success, 'Comprehend composition');
$uri = new Rfc3986();
verify($uri->ref('IPv4address')->parseAll(new ByteSource('192.0.2.1'))->success, 'RFC IPv4');
$abnf = new Rfc2234();
verify($abnf->ref('OCTET')->parseAll(new ByteSource("\xFF"))->success, 'RFC octet');

// Typed custom parser without any compatibility opt-in or fallback hook.
final class OneUnit extends Parser
{
    protected function recognize(Source $source, int $offset, ParseSession $session): AbstractMatch
    {
        $unit = $source->readUnit($offset);
        return $unit === null ? $this->failure($source, $offset)
            : $this->success($source, $offset, $unit->byteLength());
    }
    public function __toString(): string
    {
        return 'one unit';
    }
}
verify((new OneUnit())->parseAll(new Utf8Source('😀'))->consumedByteLength === 4, 'custom parser');

// Recognition does not produce effects or select alternatives by semantic validation.
$events = [];
$maps = 0;
$number = C::text('42')->mapValue(static function (ParseResult $result) use (&$maps): int {
    ++$maps;
    return (int) $result->rawText();
})->validateSemantic(static fn(ParseResult $result): bool => $result->value() > 0)
    ->onCommit(static function (ParseResult $result) use (&$events): void {
        $events[] = $result->value();
    })
    ->freeze();
$result = $number->parseAll(new Utf8Source('42'));
verify($events === [] && $maps === 0, 'pure recognition');
verify($result->value() === 42 && $result->value() === 42 && $maps === 1, 'cached typed value');
verify($result->commit() && $result->commit() && $events === [42], 'explicit idempotent commit');
verify(!$number->parseAll(new Utf8Source('42x'))->commit() && $events === [42], 'discarded effects');
$definition = new Definition(C::text('a')->setResult('item'));
$definition->addProcessor('length', static fn(array $local): int => strlen($local['item']));
$definition->addValidator(static fn(ParseResult $result): bool => $result->result('length') === 1);
$result = $definition->build()->parseAll(new ByteSource('a'));
verify($result->result('length') === 1 && $result->commit(), 'definition lifecycle');

echo 'All canonical execution examples passed.' . PHP_EOL;
