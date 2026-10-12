<?php

declare(strict_types=1);

namespace Tests\Src\Parser;

use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Tests\ParserTestCase;
use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Directive\CaseSensitive;
use Vanderlee\Comprehend\Directive\Prefer;
use Vanderlee\Comprehend\Directive\Space;
use Vanderlee\Comprehend\Library\Rfc2234;
use Vanderlee\Comprehend\Library\Rfc3986;
use Vanderlee\Comprehend\Parser\Stub;
use Vanderlee\Comprehend\Parser\Structure\All;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Except;
use Vanderlee\Comprehend\Parser\Structure\Not;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
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
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;

class SourceMatchingTest extends ParserTestCase
{
    /** @dataProvider terminalData */
    public function testTextTerminalsConsumeWholeCodePoints($parser, $text, $offset, $accepted, $length)
    {
        $result = $parser->matchSource(new Utf8Source($text), $offset);
        $this->assertResult($accepted, $length, $result);
        if ($accepted) {
            $this->assertSame($offset, $result->token->offset);
            $this->assertSame(substr($text, $offset, $length), $result->token->text);
            $this->assertTrue(mb_check_encoding($result->token->text, 'UTF-8'));
        }
    }

    public function terminalData()
    {
        return [
            [new Text("\u{00E9}a"), "\u{00E9}b", 0, false, 2],
            [new Text("\u{00E9}a"), "\u{00E9}a", 0, true, 3],
            [new Char("\u{00E9}"), "\u{00E9}a", 0, true, 2],
            [new Char('a'), "\u{00E9}a", 2, true, 1],
            [new Char('a', false), "\u{1F600}", 0, true, 4],
            [new Any(), "\u{1F600}a", 0, true, 4],
            [new Any(), "\u{1F600}a", 4, true, 1],
            [new Any(), "\u{00E9}", 2, false, 0],
            [new Set("\u{00E9}\u{1F600}"), "\u{1F600}", 0, true, 4],
            [new Set("\u{00E9}", false), "\u{00EA}", 0, true, 2],
            [new Range("\u{00E9}", "\u{00EB}"), "\u{00EA}", 0, true, 2],
            [new Range(null, "\u{00E9}"), "\u{00E9}", 0, true, 2],
            [new Range("\u{1F600}", null), "\u{1F601}", 0, true, 4],
            [new Range("\u{00E9}", "\u{00EB}", false), 'a', 0, true, 1],
            [new Regex('/./'), "\u{1F600}", 0, true, 4],
            [new Regex('/./u'), "\u{00E9}a", 2, true, 1],
            [new Regex('/a/'), 'za', 0, false, 0],
            [new Regex('/a/'), "\u{00E9}a", 2, true, 1],
            [new Regex('/a*/'), 'b', 0, false, 0],
            [new Regex('/\\\\C/'), '\\C', 0, true, 2],
            [new Regex('/\\Q\\C\\E/'), '\\C', 0, true, 2],
            [new Integer(), "\u{00E9}123", 2, true, 3],
            [new Start(), "\u{00E9}", 0, true, 0],
            [new Start(), "\u{00E9}", 2, false, 0],
            [new End(), "\u{00E9}", 2, true, 0],
            [new End(), "\u{00E9}", 0, false, 0],
            [new Nothing(), "\u{00E9}a", 2, true, 0],
        ];
    }

    public function testWrongUnicodeSuffixIsRejectedByCompleteGrammar()
    {
        $parser = new Sequence(new Text("\u{00E9}a"), new End());
        $this->assertResult(false, 2, $parser->matchSource(new Utf8Source("\u{00E9}b")));
        $this->assertResult(true, 3, $parser->matchSource(new Utf8Source("\u{00E9}a")));
    }

    public function testNestedExtractionAndCallbacksUseOriginalBytes()
    {
        $events = [];
        $first = (new Char("\u{00E9}"))->token('letter')->setResult('letter')
            ->callback(function ($text, $input, $offset, $length) use (&$events) {
                $events[] = [$text, $input, $offset, $length];
            });
        $parser = new Sequence($first, (new Any())->token('emoji')->setResult('emoji'), new End());
        $input = "z\u{00E9}\u{1F600}";
        $result = $parser->matchSource(new Utf8Source($input), 1);
        $this->assertResult(true, 6, $result);
        $this->assertSame([["\u{00E9}", $input, 1, 2]], $events);
        $this->assertSame(['letter' => "\u{00E9}", 'emoji' => "\u{1F600}"], $result->results);
        $this->assertSame([1, 3, 7], array_map(function ($token) { return $token->offset; }, $result->token->children));
        $this->assertSame([2, 4, 0], array_map(function ($token) { return $token->length; }, $result->token->children));
        $this->assertTrue(mb_check_encoding($result->token->toXml()->saveXML(), 'UTF-8'));
        $this->assertNotFalse(json_encode($result->token));
    }

    public function testMultibyteSpacingRetainsModeAndDeferredEffects()
    {
        $offsets = [];
        $spacer = (new Char("\u{00A0}"))->callback(function ($text, $input, $offset, $length) use (&$offsets) {
            $offsets[] = [$offset, $length];
        });
        $parser = (new Repeat(new Char("\u{00E9}")))->spacing($spacer);
        $this->assertResult(true, 6, $parser->matchSource(new Utf8Source("\u{00E9}\u{00A0}\u{00E9}\u{00A0}x")));
        $this->assertSame([[2, 2]], $offsets);
        $offsets = [];
        $sequence = new Space($spacer, new Sequence("\u{00E9}", 'a'));
        $this->assertFalse($sequence->matchSource(new Utf8Source("\u{00E9}\u{00A0}b"))->match);
        $this->assertSame([], $offsets);
        $this->assertTrue($sequence->matchSource(new Utf8Source("\u{00E9}\u{00A0}a"))->match);
        $this->assertSame([[2, 2]], $offsets);
    }

    public function testCombinatorsAndRulesetsPropagateSourceMode()
    {
        $letter = "\u{00E9}";
        $this->assertResult(true, 2, (new All(new Any(), new Text($letter)))->matchSource(new Utf8Source($letter)));
        $this->assertResult(true, 2, (new Except(new Any(), 'a'))->matchSource(new Utf8Source($letter)));
        $this->assertResult(true, 0, (new Not('a'))->matchSource(new Utf8Source($letter)));
        $choice = new Prefer(Prefer::LONGEST, new Choice($letter, $letter . 'a'));
        $this->assertResult(true, 3, $choice->matchSource(new Utf8Source($letter . 'a')));
        $stub = new Stub();
        $stub->parser = Choice::first('x', new Sequence('(', $stub, ')'));
        $rules = new Ruleset([Ruleset::ROOT => new Sequence($letter, $stub, new End())]);
        $this->assertResult(true, 5, $rules->matchSource(new Utf8Source($letter . '(x)')));
    }

    public function testDefinitionsValidateAndProcessWholeUtf8Text()
    {
        $text = "\u{00E9}";
        $calls = 0;
        $definition = new Definition((new Text($text))->setResult('text')->callback(function () use (&$calls) { $calls++; }));
        $definition->addValidator(function ($raw, $results) use ($text) { return $raw === $text && $results['text'] === $text; });
        $definition->addProcessor('value', function ($results) { return $results['text']; });
        $result = $definition->build()->matchSource(new Utf8Source($text));
        $this->assertResult(true, 2, $result);
        $this->assertSame($text, $result->getResult('value'));
        $this->assertSame(1, $calls);
    }

    public function testByteModePreservesOctetsAndAsciiRfcBehavior()
    {
        $parser = new Sequence(new Char(0xFF), new Range(0x80, 0xFE), new Set("\0"), new Any(), new End());
        $this->assertResult(true, 4, $parser->matchSource(new ByteSource("\xFF\xE9\0\x80")));
        $this->assertResult(true, 2, (new Text("\xC3\xA9"))->matchSource(new ByteSource("\xC3\xA9")));
        $this->assertResult(true, 1, (new Regex('/./'))->matchSource(new ByteSource("\xFF")));
        $this->assertResult(true, 1, (new Rfc2234())->OCTET->matchSource(new ByteSource("\xFF")));
        $grammar = new Sequence((new Rfc3986())->IPv4address, new End());
        $address = '255.2.3.4';
        $legacy = $grammar->match($address);
        foreach ([new ByteSource($address), new Utf8Source($address)] as $source) {
            $result = $grammar->matchSource($source);
            $this->assertResult(true, strlen($address), $result);
            $this->assertSame($legacy->token->toArray(), $result->token->toArray());
            $this->assertSame($legacy->results, $result->results);
        }
    }

    public function testCasePolicyIsIndependentOfAmbientEncoding()
    {
        $encoding = mb_internal_encoding();
        try {
            mb_internal_encoding('ISO-8859-1');
            foreach ([new Char("\u{00E9}"), new Text("\u{00E9}"), new Set("\u{00E9}"),
                new Range("\u{00E9}", "\u{00EB}"), new Regex('/\x{e9}/')] as $parser) {
                $this->assertResult(true, 2, $parser->caseInsensitive()->matchSource(new Utf8Source("\u{00C9}")));
            }
            $parser = new CaseSensitive(false, new Text("\u{0130}"));
            $result = $parser->matchSource(new Utf8Source("\u{0130}"));
            $this->assertResult(true, 2, $result);
            $this->assertSame("\u{0130}", $result->token->text);
            $this->assertResult(true, 2, (new Text('Ab'))->caseInsensitive()->matchSource(new ByteSource('aB')));
        } finally {
            mb_internal_encoding($encoding);
        }
    }

    public function testExistingRfcFixturesAgreeInBothSourceModes()
    {
        $providers = [
            ['Rfc2234Test', 'rfc2234Data'], ['Rfc4234Test', 'rfc2234Data'],
            ['Rfc5234Test', 'rfc2234Data'], ['Rfc3513Test', 'rfc3513Data'],
            ['Rfc3986Test', 'rulesData'], ['Rfc4408Test', 'rulesData'],
            ['Rfc7405Test', 'rfc7405Data'],
        ];
        foreach ($providers as [$name, $provider]) {
            require_once __DIR__ . '/../Library/' . $name . '.php';
            $class = 'Tests\\Src\\Library\\' . $name;
            foreach ((new $class())->$provider() as [$parser, $input, $accepted, $length]) {
                foreach ([new ByteSource($input), new Utf8Source($input)] as $source) {
                    $this->assertResult($accepted, $length, $parser->matchSource($source), $name . ': ' . $input);
                }
            }
        }
    }

    /** @dataProvider invalidEntryData */
    public function testInvalidTextEntryPositionsAreRejected($offset)
    {
        $this->expectException(InvalidArgumentException::class);
        (new Nothing())->matchSource(new Utf8Source("\u{00E9}"), $offset);
    }

    public function invalidEntryData()
    {
        return [[-1], [1], [3]];
    }

    /** @dataProvider mismatchData */
    public function testModeMismatchesAreErrors($parser, $source)
    {
        $this->expectException(InvalidArgumentException::class);
        $parser->matchSource($source);
    }

    public function mismatchData()
    {
        return [
            [new Char("\u{00E9}"), new ByteSource('a')],
            [new Char(0xE9), new Utf8Source('a')],
            [new Range("\u{00E9}", null), new ByteSource('a')],
            [new Range(0, 0xFF), new Utf8Source('a')],
            [new Range(0, 0xFF), new Utf8Source('')],
            [new Text("\xFF"), new Utf8Source('a')],
            [new Set("\xFF"), new Utf8Source('a')],
            [new Regex('/a/u'), new ByteSource('a')],
            [new Regex('/(*UTF)a/'), new ByteSource('a')],
            [new Regex('/(*UCP)(*UTF)a/'), new ByteSource('a')],
            [new Regex("/\xFF/"), new Utf8Source('a')],
            [new Regex('/\C/'), new Utf8Source("\u{00E9}")],
            [(new Range('a', 'z'))->caseInsensitive(), new Utf8Source("\u{0130}")],
        ];
    }

    public function testRegexErrorsAreNotOrdinaryFailures()
    {
        $limit = ini_get('pcre.backtrack_limit');
        try {
            ini_set('pcre.backtrack_limit', '1');
            $this->expectException(RuntimeException::class);
            (new Regex('/(?:a+)+$/'))->matchSource(new Utf8Source('aaaaab'));
        } finally {
            ini_set('pcre.backtrack_limit', $limit);
        }
    }

    public function testUndeclaredCustomParserIsRejectedEvenInsideGrammar()
    {
        $custom = new class('a') extends Text {};
        $this->assertResult(true, 1, $custom->match('a'));
        $this->expectException(LogicException::class);
        (new Sequence('z', $custom))->matchSource(new Utf8Source('za'));
    }

    public function testDeclaredCustomSubclassKeepsProtectedSignatureAndParentDelegation()
    {
        $custom = new class("\u{00E9}") extends Text {
            protected function supportsSource(): bool { return true; }

            protected function parse(&$input, $offset, Context $context)
            {
                return parent::parse($input, $offset, $context);
            }
        };
        $this->assertResult(true, 2, $custom->matchSource(new Utf8Source("\u{00E9}")));
    }

    public function testDeclaredCustomParserCannotReturnSplitCharacter()
    {
        $custom = new class('a') extends Text {
            protected function supportsSource(): bool { return true; }
            protected function parse(&$input, $offset, Context $context) { return $this->success($input, $offset, 1); }
        };
        $this->expectException(LogicException::class);
        $custom->matchSource(new Utf8Source("\u{00E9}"));
    }

    public function testDeclaredCustomParserCannotMutateSourceInput()
    {
        $custom = new class('a') extends Text {
            protected function supportsSource(): bool { return true; }
            protected function parse(&$input, $offset, Context $context)
            {
                $input = 'b';
                return $this->success($input, $offset, 1);
            }
        };
        $this->expectException(LogicException::class);
        $custom->matchSource(new ByteSource('a'));
    }

    public function testSourceContextRestoresNestedOverridesOnException()
    {
        $context = new Context(' ', true, Prefer::LONGEST, new Utf8Source(' a'));
        $child = new class('a') extends Text {
            protected function supportsSource(): bool { return true; }
            protected function parse(&$input, $offset, Context $context)
            {
                if ($context->isCaseSensitive() || $context->getPreference() !== Prefer::FIRST) {
                    throw new LogicException('Expected nested source policies');
                }
                throw new RuntimeException('Source child exception');
            }
        };
        $parser = new class(new CaseSensitive(false, new Prefer(Prefer::FIRST, $child))) extends Sequence {
            protected function supportsSource(): bool { return true; }
            public function run(Context $context)
            {
                $input = $context->source()->sliceBytes(0, $context->source()->byteLength());
                return $this->parseInContext($input, 0, $context);
            }
        };
        $parser->spacing('-');
        try {
            $parser->run($context);
            $this->fail('Expected source child exception');
        } catch (RuntimeException $exception) {
            $this->assertSame('Source child exception', $exception->getMessage());
        }
        $this->assertTrue($context->isCaseSensitive());
        $this->assertSame(Prefer::LONGEST, $context->getPreference());
        $this->assertSame(1, $context->skipSpacing(' a', 0));
        $context->popSpacer();
        $context->popCaseSensitivity();
        $context->popPreference();
        $this->assertSame(0, $context->skipSpacing(' a', 0));
        $this->assertFalse($context->isCaseSensitive());
        $this->assertFalse($context->getPreference());
    }
}
