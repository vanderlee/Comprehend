<?php

declare(strict_types=1);

namespace Tests\Src;

use LogicException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Comprehend;
use Vanderlee\Comprehend\Library\Rfc3986;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Stub;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\Utf8Source;

class ComprehendTest extends TestCase
{
    /** @dataProvider terminalGrammars */
    public function testTerminalFactories(Comprehend $grammar, Source $source, bool $accepted): void
    {
        $result = $grammar->named('terminal')->freeze()->parseAll($source);
        $this->assertSame($accepted, $result->success);
        $this->assertSame($accepted ? $source->byteLength() : 0, $result->consumedByteLength);
        if ($accepted) {
            $this->assertSame('terminal', $result->ast()->name);
            $this->assertSame($source->sliceBytes(0, $source->byteLength()), $result->rawText());
        }
    }

    public function terminalGrammars(): array
    {
        return [
            'Unicode character' => [Comprehend::char('é'), new Utf8Source('é'), true],
            'binary character' => [Comprehend::char("\xff"), new ByteSource("\xff"), true],
            'character mismatch' => [Comprehend::char('a'), new ByteSource('b'), false],
            'excluded character' => [Comprehend::char('a', false), new ByteSource('b'), true],
            'excluded character rejects itself' => [Comprehend::char('a', false), new ByteSource('a'), false],
            'Unicode set' => [Comprehend::set('é🙂'), new Utf8Source('🙂'), true],
            'binary set' => [Comprehend::set("\0\xff"), new ByteSource("\xff"), true],
            'set mismatch' => [Comprehend::set('ab'), new ByteSource('c'), false],
            'excluded set' => [Comprehend::set('ab', false), new ByteSource('c'), true],
            'excluded set rejects members' => [Comprehend::set('ab', false), new ByteSource('a'), false],
            'Unicode range' => [Comprehend::range('α', 'ω'), new Utf8Source('β'), true],
            'binary range' => [Comprehend::range("\x80", "\xff"), new ByteSource("\xfe"), true],
            'range mismatch' => [Comprehend::range('a', 'z'), new ByteSource('0'), false],
            'open lower bound' => [Comprehend::range(null, '9'), new ByteSource('0'), true],
            'open upper bound' => [Comprehend::range('a', null), new ByteSource('z'), true],
            'excluded range' => [Comprehend::range('a', 'z', false), new ByteSource('0'), true],
            'excluded range rejects members' => [Comprehend::range('a', 'z', false), new ByteSource('b'), false],
        ];
    }

    public function testComposedFactoriesAndRepetitionRegisterWithRulesets(): void
    {
        $item = Comprehend::choice(Comprehend::char('a'), Comprehend::range('0', '9'), Comprehend::set('!?'));
        $rules = new Ruleset();
        $rules->define('item', $item);
        $rules->root(Comprehend::sequence(
            Comprehend::text('['),
            Comprehend::ref($rules, 'item')->repeat(1, 3),
            Comprehend::text(']')->repeat(0, 1),
        ));
        $grammar = $rules->freeze();

        $this->assertTrue($grammar->parseAll(new ByteSource('[a1!'))->success);
        $this->assertTrue($grammar->parseAll(new ByteSource('[a1!]'))->success);
        $this->assertFalse($grammar->parseAll(new ByteSource('[]'))->success);
        $this->assertFalse($grammar->parseAll(new ByteSource('[aaaa]'))->success);
        $this->assertFalse($grammar->parseAll(new ByteSource('[x]'))->success);
        $this->assertTrue($item->repeat()->freeze()->parseAll(new ByteSource(''))->success);
        $this->assertTrue($item->repeat(2, 2)->freeze()->parseAll(new ByteSource('a?'))->success);
        $this->assertFalse($item->repeat(2, 2)->freeze()->parseAll(new ByteSource('a'))->success);
    }

    public function testConstructorMixesFacadeAndObjectDefinitions(): void
    {
        $rules = new Ruleset([
            'facade' => Comprehend::text('a'),
            'parser' => new Text('b'),
            'definition' => new Definition(new Text('c')),
            'factory' => static fn (): Parser => new Text('d'),
        ]);
        $rules->root(Comprehend::sequence(
            Comprehend::ref($rules, 'facade'),
            Comprehend::ref($rules, 'parser'),
            Comprehend::ref($rules, 'definition'),
            Comprehend::ref($rules, 'factory'),
        ));
        $grammar = $rules->freeze();

        $this->assertTrue($grammar->parseAll(new ByteSource('abcd'))->success);
        $this->assertFalse($grammar->parseAll(new ByteSource('abce'))->success);
    }

    public function testRegisteredFacadesKeepDeferredAndRecursiveReferences(): void
    {
        $calls = 0;
        $rules = new Ruleset();
        $rules->root(Comprehend::ref($rules, 'nested'));
        $rules->define('nested', Comprehend::choice(
            Comprehend::ref($rules, 'leaf'),
            Comprehend::sequence(
                Comprehend::text('('),
                Comprehend::ref($rules, 'nested'),
                Comprehend::text(')'),
            ),
        ));
        $rules->define('leaf', static function () use (&$calls): Parser {
            ++$calls;
            return new Text('x');
        });

        $this->assertSame(0, $calls);
        $grammar = $rules->freeze();
        $this->assertSame(1, $calls);
        $this->assertTrue($grammar->parseAll(new ByteSource('((x))'))->success);
        $this->assertFalse($grammar->parseAll(new ByteSource('((y))'))->success);
        $this->assertSame(1, $calls);
    }

    public function testRegistrationDetachesFacadeStateBetweenRulesets(): void
    {
        $facade = Comprehend::sequence(Comprehend::text('a')->named('original'));
        $first = new Ruleset(['word' => $facade]);
        $second = new Ruleset(['word' => $facade]);
        $first->root('word');
        $second->root('word');
        $retained = $first->freeze();
        $first->resolveRule('word')[0]->name('changed');

        $source = new ByteSource('a');
        $this->assertSame('changed', $first->freeze()->parseAll($source)->ast()->children[0]->children[0]->name);
        $this->assertSame('original', $retained->parseAll($source)->ast()->children[0]->children[0]->name);
        $this->assertSame('original', $second->freeze()->parseAll($source)->ast()->children[0]->children[0]->name);
        $this->assertSame('original', $facade->freeze()->parseAll($source)->ast()->children[0]->name);
    }

    public function testFacadeDefinitionsRejectFactoryArguments(): void
    {
        $rules = new Ruleset(['word' => Comprehend::text('a')]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Only rule factories accept arguments: word');
        $rules->rule('word', 'argument')->parseAll(new ByteSource('a'));
    }

    public function testForwardReferencesAndExplicitRoot()
    {
        $rules = new Ruleset();
        $rules->root('word')->define('word', new Sequence(new Text('a'), $rules->ref('suffix')))
            ->define('suffix', new Text('b'));
        $grammar = $rules->freeze();
        $this->assertTrue($grammar->parseAll(new Utf8Source('ab'))->success);
        $this->assertFalse($grammar->parseAll(new Utf8Source('ax'))->success);
        $this->assertSame([], $grammar->analysisWarnings);
    }

    public function testUndefinedReferenceFailsAtFreezeWithItsName()
    {
        $rules = new Ruleset();
        $rules->root('missing');
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('missing');
        $rules->freeze();
    }

    public function testFreezeDetachesContainersDefinitionsAndBuilderRedefinition()
    {
        $sequence = new Sequence(new Text('a'));
        $definition = new Definition($sequence);
        $definition->addProcessor('value', fn () => 1);
        $rules = new Ruleset();
        $rules->define('word', $definition)->root('word');
        $frozen = $rules->freeze();
        $sequence->add(new Text('b'));
        $definition->addProcessor('value', fn () => 2);
        $rules->define('word', new Text('c'));
        $result = $frozen->parseAll(new Utf8Source('a'));
        $this->assertTrue($result->success);
        $this->assertSame(['value' => 1], $result->results());
        $this->assertFalse($frozen->parseAll(new Utf8Source('ab'))->success);
        $this->assertTrue($rules->freeze()->parseAll(new Utf8Source('c'))->success);
    }

    public function testParameterizedFactoriesMaterializeOncePerSnapshot()
    {
        $calls = 0;
        $rules = new Ruleset();
        $rules->define('literal', function (string $text) use (&$calls): Parser { ++$calls; return new Text($text); });
        $rules->root(new Sequence($rules->ref('literal', 'a'), $rules->ref('literal', 'b'), $rules->ref('literal', 'a')));
        $frozen = $rules->freeze();
        $this->assertSame(2, $calls);
        $this->assertTrue($frozen->parseAll(new Utf8Source('aba'))->success);
        $this->assertTrue($frozen->parseAll(new Utf8Source('aba'))->success);
        $this->assertSame(2, $calls);
    }

    public function testParameterizedConsumingRecursionResolvesFiniteGraph()
    {
        $rules = new Ruleset();
        $rules->define('nested', fn (string $letter): Parser => new Choice(new Text($letter),
            new Sequence(new Text('('), $rules->ref('nested', $letter), new Text(')'))));
        $rules->root($rules->ref('nested', 'a'));
        $this->assertTrue($rules->freeze()->parseAll(new Utf8Source('((a))'))->success);
    }

    public function testByteFactoryArgumentsDoNotRequireUtf8Keys()
    {
        $rules = new Ruleset();
        $rules->define('literal', fn (string $text): Parser => new Text($text))->root($rules->ref('literal', "\xff"));
        $this->assertTrue($rules->freeze()->parseAll(new \Vanderlee\Comprehend\Source\ByteSource("\xff"))->success);
    }

    public function testRfcImportSnapshotSurvivesChanges()
    {
        $rfc = new Rfc3986();
        $rules = new Ruleset();
        $rules->define('scheme', $rfc->ref('scheme'))->root('scheme');
        $frozen = $rules->freeze();
        $rfc->define('scheme', new Text('changed'));
        $this->assertTrue($frozen->parseAll(new Utf8Source('https'))->success);
        $this->assertFalse($frozen->parseAll(new Utf8Source('1bad'))->success);
    }

    public function testFacadeExtensionsAndExportsDoNotMutateOriginal()
    {
        $parser = new Sequence(new Text('a'));
        $base = Comprehend::of($parser);
        $long = $base->then(Comprehend::text('b'));
        $choice = Comprehend::text('a');
        $extra = $choice->or(Comprehend::text('b'));
        $parser->add(new Text('x'));
        $exported = $base->parser();
        $exported->add(new Text('z'));
        $this->assertTrue($base->freeze()->parseAll(new Utf8Source('a'))->success);
        $this->assertTrue($long->freeze()->parseAll(new Utf8Source('ab'))->success);
        $this->assertFalse($choice->freeze()->parseAll(new Utf8Source('b'))->success);
        $this->assertTrue($extra->freeze()->parseAll(new Utf8Source('b'))->success);
        $this->assertTrue($choice->named('letter')->freeze()->parseAll(new Utf8Source('a'))->ast()->name === 'letter');
        $this->assertNull($choice->freeze()->parseAll(new Utf8Source('a'))->ast()->name);
    }

    /** @dataProvider unsafeGraphs */
    public function testUnsafeGraphsReportRulePaths(Parser $parser, string $message)
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($message);
        $parser->freeze();
    }

    public function unsafeGraphs(): array
    {
        $direct = new Ruleset();
        $direct->root('expression')->define('expression', new Choice($direct->ref('expression'), new Text('a')));
        $indirect = new Ruleset();
        $indirect->root('first')->define('first', $indirect->ref('second'))->define('second', $indirect->ref('first'));
        $hidden = new Stub();
        $hidden->setParser(new Sequence(new Nothing(), $hidden));
        $definition = new Definition(new Nothing());
        $selfImplementation = $definition->build();
        $definition->setGenerator($selfImplementation);
        return [[$direct, 'expression'], [$indirect, 'first'], [$hidden, 'Left-recursive'],
            [$selfImplementation, 'Left-recursive'],
            [Repeat::optional(new Nothing()), 'Nullable repetition'],
            [Repeat::plus(new Choice(new Text('a'), new Nothing())), 'Nullable repetition']];
    }

    public function testConsumingRecursionAndZeroMaximumRemainValid()
    {
        $stub = new Stub();
        $stub->setParser(new Choice(new Text('a'), new Sequence(new Text('('), $stub, new Text(')'))));
        $this->assertTrue($stub->freeze()->parseAll(new Utf8Source('((a))'))->success);
        $this->assertTrue(Repeat::exact(new Nothing(), 0)->freeze()->parseAll(new Utf8Source(''))->success);
    }

    public function testNewSnapshotsRefreshDefinitionsAndFactories()
    {
        $definition = new Definition(new Text('a'));
        $rules = new Ruleset();
        $rules->define('word', $definition)->root('word');
        $first = $rules->freeze();
        $definition->setGenerator(new Text('b'));
        $second = $rules->freeze();
        $this->assertTrue($first->parseAll(new Utf8Source('a'))->success);
        $this->assertTrue($second->parseAll(new Utf8Source('b'))->success);
    }

    public function testSnapshotsKeepDifferentReferenceScopesDistinct()
    {
        $first = new Ruleset();
        $second = new Ruleset();
        $first->define('word', new Text('a'));
        $second->define('word', new Text('b'));
        $facade = Comprehend::ref($first, 'word')->then(Comprehend::ref($second, 'word'));
        $this->assertTrue($facade->freeze()->parseAll(new Utf8Source('ab'))->success);
        $this->assertTrue($facade->parser()->freeze()->parseAll(new Utf8Source('ab'))->success);
    }

    public function testOpaqueNullableParserKeepsRuntimeProgressGuard()
    {
        $opaque = new class extends Parser {
            protected function recognize(\Vanderlee\Comprehend\Source\Source $source, int $offset,
                \Vanderlee\Comprehend\Runtime\ParseSession $session): \Vanderlee\Comprehend\Match\AbstractMatch
            {
                return $this->success($source, $offset);
            }
            public function __toString(): string { return 'opaque'; }
        };
        $frozen = Repeat::star($opaque)->freeze();
        $this->assertCount(1, $frozen->analysisWarnings);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('without consuming');
        $frozen->parseAll(new Utf8Source(''));
    }

    public function testOpaqueRecursionFailsPredictablyAtRuntime()
    {
        $opaque = new class extends Parser {
            protected function recognize(\Vanderlee\Comprehend\Source\Source $source, int $offset,
                \Vanderlee\Comprehend\Runtime\ParseSession $session): \Vanderlee\Comprehend\Match\AbstractMatch
            {
                return $this->recognizeInSession($source, $offset, $session);
            }
            public function __toString(): string { return 'opaque'; }
        };
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Left recursion at byte 0');
        $opaque->freeze()->parseAll(new Utf8Source(''));
    }
}
