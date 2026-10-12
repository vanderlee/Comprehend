<?php

namespace Tests\Src\Core;

use Error;
use RuntimeException;
use Tests\ParserTestCase;
use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Directive\CaseSensitive;
use Vanderlee\Comprehend\Directive\Prefer;
use Vanderlee\Comprehend\Directive\Space;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Char;
use Vanderlee\Comprehend\Parser\Terminal\Integer;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Range;
use Vanderlee\Comprehend\Parser\Terminal\Regex;
use Vanderlee\Comprehend\Parser\Terminal\Set;
use Vanderlee\Comprehend\Parser\Terminal\Text;

/**
 * @group core
 */
class ContextScopeTest extends ParserTestCase
{
    private function context()
    {
        $context = new Context('_', false, Prefer::SHORTEST);
        $context->pushSpacer(' ');
        $context->pushCaseSensitivity(true);
        $context->pushPreference(Prefer::LONGEST);

        return $context;
    }

    private function assertRestored(Context $context)
    {
        $this->assertTrue($context->isCaseSensitive());
        $this->assertSame(Prefer::LONGEST, $context->getPreference());
        $this->assertSame(1, $context->skipSpacing(' ', 0));
        $this->assertFalse($context->skipSpacing('_', 0));

        // Also check stack balance: no leaked override or extra pop.
        $context->popSpacer();
        $context->popCaseSensitivity();
        $context->popPreference();
        $this->assertFalse($context->isCaseSensitive());
        $this->assertSame(Prefer::SHORTEST, $context->getPreference());
        $this->assertSame(1, $context->skipSpacing('_', 0));
        $this->assertFalse($context->skipSpacing(' ', 0));
    }

    /**
     * @dataProvider scopeData
     */
    public function testNestedScopeRestoration($wrap, $scope, $outcome)
    {
        $context = $this->context();
        $exception = $outcome === 'exception' ? new RuntimeException('Child exception')
            : ($outcome === 'error' ? new Error('Child error') : null);
        $child = new ContextScopeParser(function (Context $active) use ($scope, $outcome, $exception) {
            $this->assertSame($scope !== 'case' && $scope !== 'all', $active->isCaseSensitive());
            $this->assertSame($scope === 'preference' || $scope === 'all' ? Prefer::FIRST : Prefer::LONGEST,
                $active->getPreference());
            $this->assertSame(1, $active->skipSpacing($scope === 'spacing' || $scope === 'all' ? '-' : ' ', 0));
            if ($exception !== null) {
                throw $exception;
            }

            return $outcome === 'success';
        });

        try {
            $result = ContextScopeParser::recognize($wrap($child), 'a', $context);
            $this->assertNull($exception, 'Child exception must propagate');
            $this->assertResult($outcome === 'success', $outcome === 'success' ? 1 : 0, $result);
        } catch (\Throwable $caught) {
            $this->assertSame($exception, $caught);
        }

        $this->assertRestored($context);
    }

    public function scopeData()
    {
        $wrappers = [
            'space directive' => [function ($child) { return new Space('-', $child); }, 'spacing'],
            'case directive' => [function ($child) { return new CaseSensitive(false, $child); }, 'case'],
            'preference directive' => [function ($child) { return new Prefer(Prefer::FIRST, $child); }, 'preference'],
            'sequence override' => [function ($child) { return (new Sequence($child))->spacing('-'); }, 'spacing'],
            'repeat override' => [function ($child) { return (new Repeat($child, 1, 1))->spacing('-'); }, 'spacing'],
            'choice override' => [function ($child) { return Choice::first($child); }, 'preference'],
            'nested overrides' => [function ($child) {
                return new Space('-', new CaseSensitive(false, new Prefer(Prefer::FIRST,
                    (new Sequence((new Repeat(new Choice($child), 1, 1))->spacing(true)))->spacing(true))));
            }, 'all'],
            'inherited options' => [function ($child) {
                return new Sequence(new Repeat(new Choice($child), 1, 1));
            }, 'none'],
        ];
        $cases = [];
        foreach ($wrappers as $name => [$wrap, $scope]) {
            foreach (['success', 'failure', 'exception', 'error'] as $outcome) {
                $cases[$name . ' ' . $outcome] = [$wrap, $scope, $outcome];
            }
        }

        return $cases;
    }

    public function testFailedSequenceSpacerDoesNotAffectNextAlternative()
    {
        $first = (new Sequence('a', 'b'))->spacing('-');
        $fallback = new Sequence('a', 'b');
        $parser = new Space(' ', Choice::first($first, $fallback));
        $this->assertResult(true, 3, $parser->match('a b'));
    }

    /**
     * @dataProvider spacerData
     */
    public function testSpacerFailureAndExceptionRestoreScope($repeat, $throws)
    {
        $context = $this->context();
        $exception = $throws ? new RuntimeException('Spacer exception') : null;
        $spacer = new ContextScopeParser(function () use ($exception) {
            if ($exception !== null) {
                throw $exception;
            }
            return false;
        });
        $parser = $repeat ? new Repeat('a', 2, 2) : new Sequence('a', 'a');
        $parser->spacing($spacer);
        try {
            $result = ContextScopeParser::recognize($parser, 'aa', $context);
            $this->assertNull($exception);
            $this->assertResult(false, 1, $result);
        } catch (RuntimeException $caught) {
            $this->assertSame($exception, $caught);
        }
        $this->assertRestored($context);
    }

    public function spacerData()
    {
        return [[false, false], [false, true], [true, false], [true, true]];
    }

    public function testProgressExceptionRestoresRepeatScope()
    {
        $context = $this->context();
        try {
            ContextScopeParser::recognize((new Repeat(new Nothing()))->spacing('-'), 'a', $context);
            $this->fail('Expected progress guard exception');
        } catch (\LogicException $exception) {
            $this->assertSame('Repeat child matched successfully without consuming input', $exception->getMessage());
        }
        $this->assertRestored($context);
    }

    /**
     * @dataProvider terminalData
     */
    public function testTerminalCaseScopeRestoration($terminal, $outcome)
    {
        $exception = $outcome === 'exception' ? new RuntimeException('Case exception') : null;
        $context = new class extends Context {
            public $exception;
            public $observed;

            public function isCaseSensitive()
            {
                $this->observed = parent::isCaseSensitive();
                if ($this->exception !== null) {
                    throw $this->exception;
                }
                return $this->observed;
            }
        };
        $context->exception = $exception;
        try {
            $result = ContextScopeParser::recognize($terminal->caseInsensitive(),
                $outcome === 'failure' ? 'z' : 'A', $context);
            $this->assertNull($exception);
            $this->assertResult($outcome === 'success', $outcome === 'success' ? 1 : 0, $result);
        } catch (RuntimeException $caught) {
            $this->assertSame($exception, $caught);
        }
        $this->assertFalse($context->observed);
        $context->exception = null;
        $this->assertTrue($context->isCaseSensitive());
        $context->popCaseSensitivity();
        $this->assertFalse($context->isCaseSensitive());
    }

    public function terminalData()
    {
        $cases = [];
        foreach ([new Char('a'), new Text('a'), new Set('a'), new Range('a', 'b'),
            new Regex('/a/'), new Integer(0, null, 16)] as $terminal) {
            foreach (['success', 'failure', 'exception'] as $outcome) {
                $cases[get_class($terminal) . ' ' . $outcome] = [$terminal, $outcome];
            }
        }
        return $cases;
    }
}

// Invoke protected recognition with one shared Context, without resolving output.
class ContextScopeParser extends Parser
{
    private $probe;

    public function __construct(callable $probe)
    {
        $this->probe = $probe;
    }

    public static function recognize(Parser $parser, $input, Context $context)
    {
        return $parser->parse($input, 0, $context);
    }

    protected function parse(&$input, $offset, Context $context)
    {
        return $this->makeMatch(($this->probe)($context), $input, $offset, 1);
    }

    public function __toString()
    {
        return 'context probe';
    }
}
