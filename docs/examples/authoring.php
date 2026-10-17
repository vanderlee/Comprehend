<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Vanderlee\Comprehend\Builder\FrozenGrammar;
use Vanderlee\Comprehend\Builder\FreezeFlags;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Library\Rfc3986;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Stub;
use Vanderlee\Comprehend\Parser\Terminal\Regex;
use Vanderlee\Comprehend\Parser\Terminal\Set;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Source\Utf8Source;
use Vanderlee\Comprehend\Tree\AstNode;

function arithmeticValue(AstNode $node): int
{
    if ($node->name === 'number') {
        return (int) $node->rawText();
    }
    $values = array_map(arithmeticValue(...), $node->children);
    return $node->name === 'term' ? array_product($values) : array_sum($values);
}

function arithmeticDocument(Parser $expression): Parser
{
    $trivia = Repeat::star(new Set(" \t\r\n"))->lexeme()->trivia();
    return (new Sequence($trivia, $expression, $trivia))->spacing(null)
        ->mapValue(fn(ParseResult $result): int => arithmeticValue($result->ast()->children[0]));
}

function objectArithmetic(): FrozenGrammar
{
    $expression = (new Stub())->name('expr');
    $number = (new Regex('/[0-9]+/A'))->name('number');
    $factor = new Choice($number, new Sequence(new Text('('), $expression, new Text(')')));
    $term = (new Sequence($factor, Repeat::star(new Sequence(new Text('*'), $factor))))->name('term');
    $sum = (new Sequence($term, Repeat::star(new Sequence(new Text('+'), $term))))
        ->spacing(Repeat::star(new Set(" \t\r\n")));
    $expression->setParser($sum);
    return arithmeticDocument($expression)->freeze();
}

function rulesetArithmetic(): FrozenGrammar
{
    $rules = new Ruleset();
    $rules->define('number', new Regex('/[0-9]+/A'));
    $rules->define('factor', new Choice($rules->ref('number'), new Sequence(new Text('('), $rules->ref('expr'), new Text(')'))));
    // Factor is an unnamed building block, so its reference is not projected.
    $factor = $rules->resolveRule('factor');
    $rules->define('term', new Sequence($factor, Repeat::star(new Sequence(new Text('*'), $factor))));
    $rules->define('expr', (new Sequence($rules->ref('term'), Repeat::star(new Sequence(new Text('+'), $rules->ref('term')))))
        ->spacing(Repeat::star(new Set(" \t\r\n"))));
    $rules->root(arithmeticDocument($rules->ref('expr')));
    return $rules->freeze();
}

function facadeArithmetic(): FrozenGrammar
{
    $rules = new Ruleset();
    $spacing = C::set(" \t\r\n")->star();
    // Inherited spacing belongs between tokens, never between a number's digits.
    $rules->define('number', C::range('0', '9')->plus()->lexeme());
    $factor = C::ref($rules, 'number')->or(
        C::sequence('(', $rules->rule('expr'), ')'),
    );
    $rules->define('term', $factor->then(C::text('*')->then($factor)->star()));
    $rules->define('expr', C::ref($rules, 'term')
        ->then(C::text('+')->then(C::ref($rules, 'term'))->star())
        ->spacing($spacing));
    $trivia = $spacing->lexeme()->trivia();
    $rules->root(C::sequence($trivia, C::ref($rules, 'expr'), $trivia)
        ->spacing(null)
        ->mapValue(fn(ParseResult $result): int => arithmeticValue($result->ast()->firstNamed('expr'))));
    return $rules->freeze();
}

/** Exact named shape and byte spans, independent of underlying combinator classes. */
function authoringAstShape(AstNode $node): array
{
    return [$node->name, $node->group, $node->span->start, $node->span->end,
        array_map(authoringAstShape(...), $node->children)];
}

$arithmetic = [objectArithmetic(), rulesetArithmetic(), facadeArithmetic()];
foreach (['2+3*4' => 14, '(2+3)*4' => 20, " 2 * (3 + 4) \n" => 14, '42' => 42] as $text => $expected) {
    $shape = null;
    foreach ($arithmetic as $grammar) {
        $result = $grammar->parseAll(new Utf8Source((string) $text));
        if (!$result->success || $result->value() !== $expected || $result->rawText() !== (string) $text) {
            throw new RuntimeException('Arithmetic authoring disagreement: ' . $text);
        }
        $current = authoringAstShape($result->ast());
        if ($shape !== null && $shape !== $current) {
            throw new RuntimeException('Arithmetic AST disagreement');
        }
        $shape = $current;
    }
}
foreach (['2+', '*3', '(2+3', '2 3', '2+bad'] as $text) {
    foreach ($arithmetic as $grammar) {
        if ($grammar->parseAll(new Utf8Source($text))->success) {
            throw new RuntimeException('Malformed arithmetic accepted: ' . $text);
        }
    }
}

$rfc = new Rfc3986();
$address = new Sequence($rfc->ref('scheme'), new Text('://'), $rfc->ref('host'));
$rules = new Ruleset();
$rules->define('address', $address)->root('address');
$addresses = [
    (new Sequence((clone $address)->name('address')))->freeze(),
    $rules->freeze(),
    C::sequence(C::ref($rfc, 'scheme')
        ->then('://', $rfc->rule('host'))->named('address'))->freeze(),
];
foreach (['https://example.com' => true, 'http://127.0.0.1' => true, '1bad://host' => false, 'http://host/path' => false] as $text => $accepted) {
    $shape = null;
    foreach ($addresses as $grammar) {
        $result = $grammar->parseAll(new Utf8Source($text));
        if ($result->success !== $accepted) {
            throw new RuntimeException('RFC authoring disagreement: ' . $text);
        }
        if ($accepted) {
            $current = authoringAstShape($result->ast());
            if ($shape !== null && $shape !== $current) {
                throw new RuntimeException('RFC AST disagreement');
            }
            if ($result->rawText() !== $text) {
                throw new RuntimeException('RFC source reconstruction failed');
            }
            $shape = $current;
        }
    }
}
$letters = C::set('ab')->many()->named('letters');
$preparedLetters = [
    $letters->freeze(),
    $letters->freeze(flags: FreezeFlags::NONE),
    FrozenGrammar::from($letters, FreezeFlags::OPTIMIZE),
];
foreach ($preparedLetters as $grammar) {
    $result = $grammar->parse('abba');
    if (!$result->success || $result->rawText() !== 'abba' || $result->node?->name !== 'letters') {
        throw new RuntimeException('Freeze flags changed recognition or naming');
    }
}

echo "Object, Ruleset and facade arithmetic/RFC acceptance, values, ASTs and spans agree.\n";
