<?php

declare(strict_types=1);

namespace Tests\Src\Parser;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Compatibility\LegacyParserAdapter;
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
        $diagnostic = (new Sequence('a', new Choice('b', 'c', 'b')))->parseAll(new Utf8Source('ax'))->diagnostic;
        $this->assertSame(1, $diagnostic->byteOffset);
        $this->assertSame(["'b'", "'c'"], $diagnostic->expected);
        $this->assertSame([['Sequence', 'Choice', 'Char']], $diagnostic->rulePaths);
    }

    /** @dataProvider probeData */
    public function testIrrelevantProbesAreSuppressed($probe)
    {
        $result = (new Sequence($probe, 'z'))->parseAll(new Utf8Source('abx'));
        $this->assertSame(0, $result->farthestFailure);
        $this->assertSame(["'z'"], $result->diagnostic->expected);
    }

    public function probeData(): array
    {
        return [[Repeat::optional('abc')], [Repeat::star('abc')], [new Not('abc')]];
    }

    public function testRequiredRepetitionRetainsFailure()
    {
        $result = Repeat::plus('abc')->parseAll(new Utf8Source('abx'));
        $this->assertSame(2, $result->farthestFailure);
        $this->assertSame(['"abc"'], $result->diagnostic->expected);
    }

    public function testTerminationAfterMinimumAndSuccessfulExclusionDiscardProbes()
    {
        $result = (new Sequence(Repeat::plus('ab'), 'z'))->parseAll(new Utf8Source('abax'));
        $this->assertSame(2, $result->farthestFailure);
        $this->assertSame(["'z'"], $result->diagnostic->expected);
        $result = (new Sequence(new Except('a', 'abcd'), 'z'))->parseAll(new Utf8Source('abcx'));
        $this->assertSame(1, $result->farthestFailure);
        $this->assertSame(["'z'"], $result->diagnostic->expected);
    }

    public function testEofMergesTiesButRetainsFartherChoiceFailure()
    {
        $result = Choice::first('ab', 'a')->parseAll(new Utf8Source('ax'));
        $this->assertSame(['"ab"', 'end'], $result->diagnostic->expected);
        $result = Choice::first('abcd', 'a')->parseAll(new Utf8Source('abcx'));
        $this->assertSame(3, $result->diagnostic->byteOffset);
        $this->assertSame(['"abcd"'], $result->diagnostic->expected);
    }

    public function testNegativeLookaheadFailureReportsItsStart()
    {
        $result = (new Not('abc'))->parseAll(new Utf8Source('abc'));
        $this->assertSame(0, $result->farthestFailure);
        $this->assertSame(['Not'], $result->diagnostic->expected);
        $result = (new Except('abc', 'abc'))->parseAll(new Utf8Source('abc'));
        $this->assertSame(0, $result->farthestFailure);
        $this->assertSame(['Except'], $result->diagnostic->expected);
    }

    public function testEofExpectationAndAdapterMetadata()
    {
        $result = Repeat::star('a')->parseAll(new Utf8Source('aax'));
        $this->assertSame(2, $result->farthestFailure);
        $this->assertSame(['end'], $result->diagnostic->expected);
        $match = (new LegacyParserAdapter(new Text('a')))->parseAll('ab');
        $this->assertSame(['end'], $match->diagnostic->expected);
    }

    public function testNamedRulePath()
    {
        $rules = new Ruleset();
        $rules->define('word', new Text('abc'));
        $diagnostic = $rules->word()->parseAll(new Utf8Source('abx'))->diagnostic;
        $this->assertContains('word', $diagnostic->rulePaths[0]);
    }

    public function testSpacingRetainsExpectedSymbol()
    {
        $diagnostic = (new Sequence('a', 'b'))->spacing(new Text(' -'))->parseAll(new Utf8Source('a x'))->diagnostic;
        $this->assertSame(2, $diagnostic->byteOffset);
        $this->assertSame(['" -"'], $diagnostic->expected);
    }

    public function testNewlinesByteColumnsAndEmptyEofExcerpt()
    {
        $source = new Utf8Source("é\r\nx\ry\nz\n");
        foreach ([4 => [2, 1, 'x'], 6 => [3, 1, 'y'], 8 => [4, 1, 'z'], 10 => [5, 1, '']] as $offset => [$line, $column, $excerpt]) {
            $diagnostic = (new Text('!'))->parseAll($source, $offset)->diagnostic;
            $this->assertSame(['line' => $line, 'column' => $column], $diagnostic->location());
            $this->assertSame($excerpt, $diagnostic->excerpt());
        }
        $diagnostic = (new Text('a'))->parseAll(new ByteSource("\xffx"), 1)->diagnostic;
        $this->assertSame(['line' => 1, 'column' => 2], $diagnostic->location());
        $this->assertSame("\xffx", $diagnostic->excerpt());
    }
}
