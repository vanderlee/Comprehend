<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Builder\FreezeFlags;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Directive\CaseSensitive;
use Vanderlee\Comprehend\Library\Rfc2234;
use Vanderlee\Comprehend\Library\Rfc3986;
use Vanderlee\Comprehend\Library\Rfc4291;
use Vanderlee\Comprehend\Library\Rfc5322;
use Vanderlee\Comprehend\Library\Rfc8259;
use Vanderlee\Comprehend\Library\Xml;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Set;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ChoicePolicy;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8FileSource;
use Vanderlee\Comprehend\Source\Utf8Source;

test('library optimized and ordinary graphs agree on valid malformed and warm repeated inputs', function (
    string $class,
    string $rule,
    array $inputs,
) {
    $grammar = (new $class())->rule($rule);
    $plain = $grammar->freeze(FreezeFlags::NONE);
    $optimized = $grammar->freeze();
    for ($pass = 0; $pass < 2; ++$pass) {
        foreach ($inputs as $input) {
            foreach ([new ByteSource($input), new Utf8Source($input)] as $source) {
                foreach (['matchPrefix', 'parseAll'] as $operation) {
                    $direct = $plain->$operation($source);
                    $result = $optimized->$operation($source);
                    expect($result->success)->toBe($direct->success)->and($result->node)->toEqual($direct->node)
                        ->and($result->diagnostic)->toEqual($direct->diagnostic)
                        ->and($result->farthestFailure)->toBe($direct->farthestFailure)
                        ->and($result->consumedByteLength)->toBe($direct->consumedByteLength)
                        ->and($result->ast())->toEqual($direct->ast())
                        ->and($result->value())->toBe($direct->value())
                        ->and($result->results())->toBe($direct->results());
                }
            }
        }
    }
})->with([
    [Rfc3986::class, 'URI', ['https://example.com/a%20b/aa?x=%2F#abc', 'https://example.com/%',
        'https://example.com/%A', 'https://example.com/%GG', 'https://example.com/a%20b?!', 'x:a']],
    [Rfc3986::class, 'port', ['123123123', '12!', '', '001001']],
    [Rfc2234::class, 'char_val', ['"aaaa bbbb"', '"ab"!', '"unterminated', '""']],
    [Rfc4291::class, 'ipv6_address', ['2001:db8::1', '::1', '::ffff:192.0.2.1', '2001:::1',
        '1:2:3:4:5:6:7:8', '1:2:3:4:5:6:7:8:9', '2001:db8:abcd:1234::']],
    [Xml::class, 'document', ['<a><![CDATA[aaaa]]></a>', '<a><!--aaaa--></a>', '<a><![CDATA[bad</a>']],
    [Rfc5322::class, 'address_list', ['a@example.com,b@example.com', 'a@example.com,', '"a" <a@example.com>']],
    [Rfc8259::class, 'json_text', ['[1,2,3]', '[1,2,]', '{"a":1,"b":2}', '[123,', '[true,false,null]']],
]);

test('templates replay successful choice failures under each caller path and inherited diagnostic', function () {
    $rules = new Ruleset(['unit' => C::choice('abc', 'a', 'b')]);
    $repeat = Repeat::star($rules->ref('unit'))->tag('run');
    $parser = Choice::first(new Sequence(new Text('x'), $repeat), new Sequence(new Text('y'), $repeat));
    $plain = $parser->freeze(FreezeFlags::NONE);
    $optimized = $parser->freeze();
    foreach (['xaaba!', 'yaaba!', 'xabcab!', 'yabcab!', 'xaaba!'] as $text) {
        $source = new ByteSource($text);
        $first = $plain->parse($source);
        $second = $optimized->parse($source);
        expect($second->diagnostic)->toEqual($first->diagnostic)->and($second->node)->toEqual($first->node);
    }
});

test('lexical templates distinguish case choice modes source mode and end of input', function () {
    foreach ([ChoicePolicy::FIRST, ChoicePolicy::LONGEST, ChoicePolicy::SHORTEST] as $preference) {
        $child = (new Choice(new Text('ab'), new Text('a'), new Set('bA')))->setPreference($preference);
        $repeat = Repeat::star($child);
        $parser = new Choice(
            new Sequence(new Text('x'), new CaseSensitive(true, $repeat)),
            new Sequence(new Text('y'), new CaseSensitive(false, $repeat)),
        );
        $plain = $parser->freeze(FreezeFlags::NONE);
        $optimized = $parser->freeze();
        foreach (['xaab', 'yaaB', 'xaaba!', 'yaABA!', 'xa', 'ya', 'xaab', "ya\u{e9}"] as $text) {
            foreach ([new ByteSource($text), new Utf8Source($text)] as $source) {
                $first = $plain->parse($source);
                $second = $optimized->parse($source);
                expect($second->success)->toBe($first->success)->and($second->node)->toEqual($first->node)
                    ->and($second->diagnostic)->toEqual($first->diagnostic);
            }
        }
    }
});

test('named executable metadata is never converted to lexical templates', function () {
    $guards = 0;
    $effects = 0;
    $rules = new Ruleset(['unit' => C::set('ab')->guard(static function () use (&$guards) {
        ++$guards;
        return true;
    })->onCommit(static function () use (&$effects) {
        ++$effects;
    })]);
    $grammar = $rules->rule('unit')->many()->freeze();
    $result = $grammar->parse('abab');
    expect($guards)->toBe(4)->and($effects)->toBe(0);
    $result->commit();
    $result->commit();
    expect($effects)->toBe(4);
    $grammar->parse('abab');
    expect($guards)->toBe(8);
});

test('warmed lexical tables still use ordinary budget paths and preserve file source spans', function () {
    $rules = new Ruleset(['unit' => C::choice('abc', 'a', 'b')]);
    $parser = Repeat::star($rules->ref('unit'));
    $plain = $parser->freeze(FreezeFlags::NONE);
    $optimized = $parser->freeze();
    $optimized->parse('abababc');
    foreach ([new ParseLimits(maxWork: 8), new ParseLimits(maxDepth: 2)] as $limits) {
        $errors = [];
        foreach ([$plain, $optimized] as $grammar) {
            try {
                $grammar->parse('abababc', limits: $limits);
            } catch (ParseLimitException $error) {
                $errors[] = $error->getMessage();
            }
        }
        expect($errors)->toHaveCount(2)->and($errors[1])->toBe($errors[0]);
    }
    $stream = tmpfile();
    if ($stream === false) {
        throw new RuntimeException('Could not create source fixture');
    }
    fwrite($stream, '#abababc!');
    $source = new Utf8FileSource($stream, 4);
    fclose($stream);
    try {
        expect($optimized->matchPrefix($source, 1)->node)->toEqual($plain->matchPrefix($source, 1)->node)
            ->and($optimized->parse($source, 1)->diagnostic)->toEqual($plain->parse($source, 1)->diagnostic);
    } finally {
        $source->close();
    }
});

test('lexical choice tables support string keys and named graphs with explicit spacing', function () {
    $choice = new Choice(new Text('abc'));
    $choice['other'] = new Text('ab');
    $parser = Repeat::star($choice)->spacing(Repeat::star(new Text(' ')));
    $plain = $parser->freeze(FreezeFlags::NONE);
    $optimized = $parser->freeze();
    foreach (['ab ab', 'abc ab', 'ab abc!'] as $text) {
        $source = new ByteSource($text);
        expect($optimized->parse($source)->node)->toEqual($plain->parse($source)->node)
            ->and($optimized->parse($source)->diagnostic)->toEqual($plain->parse($source)->diagnostic);
    }
});
