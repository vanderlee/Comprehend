<?php

declare(strict_types=1);

namespace Tests\features\parsing;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Except;
use Vanderlee\Comprehend\Parser\Structure\Not;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;

class DiagnosticTest extends ParserTestCase
{
    public function testTerminalSubclassOutsideTerminalNamespaceRetainsStringExpectation(): void
    {
        $parser = new class ('abc') extends Text {
            public function __toString(): string
            {
                return 'custom literal';
            }
        };
        $parser->diagnosticRule('word');
        $result = $parser->parseAll(new ByteSource('abx'));

        $this->assertFalse($result->success);
        $this->assertSame(2, $result->diagnostic->byteOffset);
        $this->assertSame(['custom literal'], $result->diagnostic->expected);
        $this->assertSame([['word']], $result->diagnostic->rulePaths);
    }

    public function testUtf8FailureAndLazyLocation()
    {
        $result = (new Text("éa"))->parseAll(new Utf8Source("éb"));
        $diagnostic = $result->diagnostic;
        $this->assertFalse($result->success);
        $this->assertSame(2, $diagnostic->byteOffset);
        $this->assertSame(['"éa"'], $diagnostic->expected);
        $this->assertSame(['line' => 1, 'column' => 2], $diagnostic->location());
        $this->assertSame('éb', $diagnostic->excerpt());
        $this->assertSame($diagnostic->location(), $diagnostic->location());
        $this->assertStringContainsString('byte 2', $diagnostic->message());
    }

    public function testTiedExpectationsAndPaths()
    {
        $diagnostic = (new Sequence(
            new Text('a'),
            new Choice(new Text('b'), new Text('c'), new Text('b')),
        ))->parseAll(new Utf8Source('ax'))->diagnostic;
        $this->assertSame(1, $diagnostic->byteOffset);
        $this->assertSame(['"b"', '"c"'], $diagnostic->expected);
        $this->assertSame([['Sequence', 'Choice', 'Text']], $diagnostic->rulePaths);
    }

    /** @dataProvider probeData */
    public function testIrrelevantProbesAreSuppressed($probe)
    {
        $result = (new Sequence($probe, new Text('z')))->parseAll(new Utf8Source('abx'));
        $this->assertSame(0, $result->farthestFailure);
        $this->assertSame(['"z"'], $result->diagnostic->expected);
    }

    public function probeData(): array
    {
        return [[Repeat::optional(new Text('abc'))], [Repeat::star(new Text('abc'))], [new Not(new Text('abc'))]];
    }

    public function testRequiredRepetitionRetainsFailure()
    {
        $result = Repeat::plus(new Text('abc'))->parseAll(new Utf8Source('abx'));
        $this->assertSame(2, $result->farthestFailure);
        $this->assertSame(['"abc"'], $result->diagnostic->expected);
    }

    public function testTerminationAfterMinimumAndSuccessfulExclusionDiscardProbes()
    {
        $result = (new Sequence(Repeat::plus(new Text('ab')), new Text('z')))->parseAll(new Utf8Source('abax'));
        $this->assertSame(2, $result->farthestFailure);
        $this->assertSame(['"z"'], $result->diagnostic->expected);
        $result = (new Sequence(
            new Except(new Text('a'), new Text('abcd')),
            new Text('z'),
        ))->parseAll(new Utf8Source('abcx'));
        $this->assertSame(1, $result->farthestFailure);
        $this->assertSame(['"z"'], $result->diagnostic->expected);
    }

    public function testEofMergesTiesButRetainsFartherChoiceFailure()
    {
        $result = Choice::first(new Text('ab'), new Text('a'))->parseAll(new Utf8Source('ax'));
        $this->assertSame(['"ab"', 'end'], $result->diagnostic->expected);
        $result = Choice::first(new Text('abcd'), new Text('a'))->parseAll(new Utf8Source('abcx'));
        $this->assertSame(3, $result->diagnostic->byteOffset);
        $this->assertSame(['"abcd"'], $result->diagnostic->expected);
    }

    public function testNegativeLookaheadFailureReportsItsStart()
    {
        $result = (new Not(new Text('abc')))->parseAll(new Utf8Source('abc'));
        $this->assertSame(0, $result->farthestFailure);
        $this->assertSame(['Not'], $result->diagnostic->expected);
        $result = (new Except(new Text('abc'), new Text('abc')))->parseAll(new Utf8Source('abc'));
        $this->assertSame(0, $result->farthestFailure);
        $this->assertSame(['Except'], $result->diagnostic->expected);
    }

    public function testNamedRulePath()
    {
        $rules = new Ruleset();
        $rules->define('word', new Text('abc'));
        $diagnostic = $rules->ref('word')->parseAll(new Utf8Source('abx'))->diagnostic;
        $this->assertContains('word', $diagnostic->rulePaths[0]);
    }

    public function testSpacingRetainsExpectedSymbol()
    {
        $diagnostic = (new Sequence(
            new Text('a'),
            new Text('b'),
        ))->spacing(new Text(' -'))->parseAll(new Utf8Source('a x'))->diagnostic;
        $this->assertSame(2, $diagnostic->byteOffset);
        $this->assertSame(['" -"'], $diagnostic->expected);
    }

    public function testNewlinesByteColumnsAndEmptyEofExcerpt()
    {
        $source = new Utf8Source("é\r\nx\ry\nz\n");
        foreach (
            [
                4 => [2, 1, 'x'],
                6 => [3, 1, 'y'],
                8 => [4, 1, 'z'],
                10 => [5, 1, ''],
            ] as $offset => [$line, $column, $excerpt]
        ) {
            $diagnostic = (new Text('!'))->parseAll($source, $offset)->diagnostic;
            $this->assertSame(['line' => $line, 'column' => $column], $diagnostic->location());
            $this->assertSame($excerpt, $diagnostic->excerpt());
        }
        $diagnostic = (new Text('a'))->parseAll(new ByteSource("\xffx"), 1)->diagnostic;
        $this->assertSame(['line' => 1, 'column' => 2], $diagnostic->location());
        $this->assertSame("\xffx", $diagnostic->excerpt());
    }

    public function testNewlineAtReadChunkBoundary()
    {
        $prefix = str_repeat('a', 8191);
        foreach (["\r\nx" => 8193, "\rx" => 8192, "\r" => 8192] as $suffix => $offset) {
            $source = new ByteSource($prefix . $suffix);
            $diagnostic = (new Text('!'))->parseAll($source, $offset)->diagnostic;

            $this->assertSame(['line' => 2, 'column' => 1], $diagnostic->location());
            $this->assertSame($suffix === "\r" ? '' : 'x', $diagnostic->excerpt());
            $this->assertSame(['line' => 2, 'column' => 1], $diagnostic->location());
        }
    }
}
