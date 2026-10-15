<?php

declare(strict_types=1);

namespace Tests\features\sources;

use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Tests\ParserTestCase;
use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Directive\CaseSensitive;
use Vanderlee\Comprehend\Directive\Prefer;
use Vanderlee\Comprehend\Directive\Space;
use Vanderlee\Comprehend\Library\Rfc2234;
use Vanderlee\Comprehend\Library\Rfc3986;
use Vanderlee\Comprehend\Parser\Structure\All;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Except;
use Vanderlee\Comprehend\Parser\Structure\Not;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Stub;
use Vanderlee\Comprehend\Parser\Terminal\Any;
use Vanderlee\Comprehend\Parser\Terminal\Char;
use Vanderlee\Comprehend\Parser\Terminal\End;
use Vanderlee\Comprehend\Parser\Terminal\Integer;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Range;
use Vanderlee\Comprehend\Parser\Terminal\Regex;
use Vanderlee\Comprehend\Parser\Terminal\Set;
use Vanderlee\Comprehend\Parser\Terminal\Start;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ChoicePolicy;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;

abstract class SourceMatchingTest extends ParserTestCase
{
    public static function terminalData()
    {
        return [
            [new Text("éa"), "éb", 0, false, 2],
            [new Text("éa"), "éa", 0, true, 3],
            [new Char("é"), "éa", 0, true, 2],
            [new Char('a'), "éa", 2, true, 1],
            [new Char('a', false), "😀", 0, true, 4],
            [new Any(), "😀a", 0, true, 4],
            [new Any(), "😀a", 4, true, 1],
            [new Any(), "é", 2, false, 0],
            [new Set("é😀"), "😀", 0, true, 4],
            [new Set("é", false), "ê", 0, true, 2],
            [new Range("é", "ë"), "ê", 0, true, 2],
            [new Range(null, "é"), "é", 0, true, 2],
            [new Range("😀", null), "😁", 0, true, 4],
            [new Range("é", "ë", false), 'a', 0, true, 1],
            [new Regex('/./'), "😀", 0, true, 4],
            [new Regex('/./u'), "éa", 2, true, 1],
            [new Regex('/a/'), 'za', 0, false, 0],
            [new Regex('/a/'), "éa", 2, true, 1],
            [new Regex('/a*/'), 'b', 0, false, 0],
            [new Regex('/\\\\C/'), '\C', 0, true, 2],
            [new Regex('/\Q\C\E/'), '\C', 0, true, 2],
            [new Integer(), "é123", 2, true, 3],
            [new Start(), "é", 0, true, 0],
            [new Start(), "é", 2, false, 0],
            [new End(), "é", 2, true, 0],
            [new End(), "é", 0, false, 0],
            [new Nothing(), "éa", 2, true, 0],
        ];
    }

    public static function invalidEntryData()
    {
        return [[-1], [1], [3]];
    }

    public static function mismatchData()
    {
        return [
            [new Char("é"), new ByteSource('a')],
            [new Char(chr(0xe9)), new Utf8Source('a')],
            [new Range("é", null), new ByteSource('a')],
            [new Range(chr(0), chr(0xff)), new Utf8Source('a')],
            [new Range(chr(0), chr(0xff)), new Utf8Source('')],
            [new Text("\xff"), new Utf8Source('a')],
            [new Set("\xff"), new Utf8Source('a')],
            [new Regex('/a/u'), new ByteSource('a')],
            [new Regex('/(*UTF)a/'), new ByteSource('a')],
            [new Regex('/(*UCP)(*UTF)a/'), new ByteSource('a')],
            [new Regex("/\xff/"), new Utf8Source('a')],
            [new Regex('/\C/'), new Utf8Source("é")],
            [(new Range('a', 'z'))->caseInsensitive(), new Utf8Source("İ")],
        ];
    }
}

uses(SourceMatchingTest::class);

test('text terminals consume whole code points', function ($parser, $text, $offset, $accepted, $length): void {
    $source = new Utf8Source($text);
    $result = $parser->matchPrefix($source, $offset);
    $this->assertResult($accepted, $length, $result);
    $this->assertSame($source, $result->span->source);
    $this->assertSame($offset, $result->start);
    if ($accepted) {
        $this->assertSame(substr($text, $offset, $length), $result->rawText());
        $this->assertSame($result->rawText(), $result->node->rawText());
        $this->assertTrue(mb_check_encoding($result->rawText(), 'UTF-8'));
    }
})->with(SourceMatchingTest::terminalData());

test('nested outputs and committed callbacks use original bytes', function (): void {
    $events = [];
    $letter = (new Char('é'))->name('letter')->setResult('letter')
        ->onCommit(static function (ParseResult $result) use (&$events) {
            $events[] = [$result->rawText(), $result->start, $result->consumedByteLength];
        });
    $result = (new Sequence($letter, (new Any())->name('emoji')->setResult('emoji'), new End()))
        ->parseAll(new Utf8Source('zé😀'), 1);
    $this->assertResult(true, 6, $result);
    $this->assertSame([], $events);
    $this->assertSame(['letter' => 'é', 'emoji' => '😀'], $result->results());
    $this->assertSame([1, 3, 7], array_map(static fn($node) => $node->span->start, $result->node->children));
    $this->assertSame([2, 4, 0], array_map(static fn($node) => $node->span->byteLength(), $result->node->children));
    $this->assertTrue($result->commit());
    $this->assertSame([['é', 1, 2]], $events);
});

test('byte mode preserves octets across terminal families', function (): void {
    $source = new ByteSource("\xFF");
    foreach (
        [new Text("\xFF"), new Char("\xFF"), new Any(), new Set("\0\xFF"),
            new Range("\x80", "\xFF"), new Regex('/./s')] as $parser
    ) {
        $result = $parser->parseAll($source);
        $this->assertResult(true, 1, $result);
        $this->assertSame("\xFF", $result->node->rawText());
    }
    $abnf = new Rfc2234();
    $this->assertTrue($abnf->ref('OCTET')->parseAll($source)->success);
});

test('case policy does not depend on ambient encoding', function (): void {
    $previous = mb_internal_encoding();
    try {
        mb_internal_encoding('ISO-8859-1');
        foreach (
            [(new Text('É'))->caseInsensitive(), (new Char('É'))->caseInsensitive(),
                (new Set('É'))->caseInsensitive(), (new Range('É', 'Ê'))->caseInsensitive()] as $parser
        ) {
            $this->assertResult(true, 2, $parser->parseAll(new Utf8Source('é')));
        }
    } finally {
        mb_internal_encoding($previous);
    }
});

test('wrong unicode suffix is rejected by complete grammar', function () {
    $parser = new Sequence(new Text("éa"), new End());
    $this->assertResult(false, 2, $parser->matchPrefix(new Utf8Source("éb")));
    $this->assertResult(true, 3, $parser->matchPrefix(new Utf8Source("éa")));
});

test('multibyte spacing retains mode and deferred effects', function () {
    $offsets = [];
    $spacer = (new Char(" "))->onCommit(
        function (ParseResult $result) use (&$offsets) {
            $offsets[] = [$result->start, $result->consumedByteLength];
        },
    );
    $parser = (new Repeat(new Char("é")))->spacing($spacer);
    $result = $parser->matchPrefix(new Utf8Source("é é x"));
    $this->assertResult(true, 6, $result);
    $this->assertSame([], $offsets);
    $result->commit();
    $this->assertSame([[2, 2]], $offsets);
    $offsets = [];
    $sequence = new Space($spacer, new Sequence(new Text("é"), new Text('a')));
    $this->assertFalse($sequence->parseAll(new Utf8Source("é b"))->commit());
    $this->assertSame([], $offsets);
    $this->assertTrue($sequence->parseAll(new Utf8Source("é a"))->commit());
    $this->assertSame([[2, 2]], $offsets);
});

test('combinators and rulesets propagate source mode', function () {
    $letter = "é";
    $this->assertResult(true, 2, (new All(new Any(), new Text($letter)))->matchPrefix(new Utf8Source($letter)));
    $this->assertResult(true, 2, (new Except(new Any(), new Text('a')))->matchPrefix(new Utf8Source($letter)));
    $this->assertResult(true, 0, (new Not(new Text('a')))->matchPrefix(new Utf8Source($letter)));
    $choice = new Prefer(ChoicePolicy::LONGEST, new Choice(new Text($letter), new Text($letter . 'a')));
    $this->assertResult(true, 3, $choice->matchPrefix(new Utf8Source($letter . 'a')));
    $stub = new Stub();
    $stub->setParser(Choice::first(new Text('x'), new Sequence(new Text('('), $stub, new Text(')'))));
    $rules = new Ruleset([Ruleset::ROOT => new Sequence(new Text($letter), $stub, new End())]);
    $this->assertResult(true, 5, $rules->matchPrefix(new Utf8Source($letter . '(x)')));
});

test('definitions validate and process whole utf8 text', function () {
    $text = "é";
    $calls = 0;
    $definition = new Definition(
        (new Text($text))->setResult('text')->onCommit(
            function () use (&$calls) {
                $calls++;
            },
        ),
    );
    $definition->addValidator(
        function (ParseResult $result) use ($text) {
            return $result->rawText() === $text && $result->result('value') === $text;
        },
    );
    $definition->addProcessor(
        'value',
        function ($results) {
            return $results['text'];
        },
    );
    $result = $definition->build()->matchPrefix(new Utf8Source($text));
    $this->assertResult(true, 2, $result);
    $this->assertSame($text, $result->result('value'));
    $this->assertSame(0, $calls);
    $this->assertTrue($result->commit());
    $this->assertSame(1, $calls);
});

test('existing rfc fixtures agree in both source modes', function () {
    $providers = [
        ['Rfc2234TestCase', 'rfc2234Data'],
        ['Rfc4234TestCase', 'rfc2234Data'],
        ['Rfc5234TestCase', 'rfc2234Data'],
        ['Rfc3513TestCase', 'rfc3513Data'],
        ['Rfc3986TestCase', 'rulesData'],
        ['Rfc4408TestCase', 'rulesData'],
        ['Rfc7405TestCase', 'rfc7405Data'],
    ];
    foreach ($providers as [$name, $provider]) {
        $class = 'Tests\Fixtures\\' . $name;
        foreach ($class::{$provider}() as [$parser, $input, $accepted, $length]) {
            foreach ([new ByteSource($input), new Utf8Source($input)] as $source) {
                $this->assertResult($accepted, $length, $parser->matchPrefix($source), $name . ': ' . $input);
            }
        }
    }
});

test('invalid text entry positions are rejected', function ($offset) {
    $this->expectException(InvalidArgumentException::class);
    (new Nothing())->matchPrefix(new Utf8Source("é"), $offset);
})->with(SourceMatchingTest::invalidEntryData());

test('mode mismatches are errors', function ($parser, $source) {
    $this->expectException(InvalidArgumentException::class);
    $parser->matchPrefix($source);
})->with(SourceMatchingTest::mismatchData());

test('regex errors are not ordinary failures', function () {
    $limit = ini_get('pcre.backtrack_limit');
    try {
        ini_set('pcre.backtrack_limit', '1');
        $this->expectException(RuntimeException::class);
        (new Regex('/(?:a+)+$/'))->matchPrefix(new Utf8Source('aaaaab'));
    } finally {
        ini_set('pcre.backtrack_limit', $limit);
    }
});
