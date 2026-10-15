<?php

declare(strict_types=1);

namespace Tests\Src\Parser;

use LogicException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\FailureDiagnosticAtStart;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\Utf8Source;

final class ParserTest extends TestCase
{
    public function testBaseRuntimeBindsCustomRecognitionToTheSource(): void
    {
        $parser = $this->oneByteParser();
        $source = new ByteSource('ab');
        $result = $parser->matchPrefix($source, 1);

        $this->assertTrue($result->success);
        $this->assertSame('b', $result->rawText());
        $this->assertSame($source, $result->span->source);
        $this->assertSame([1, 2], [$result->start, $result->end]);
    }

    public function testDefaultChildrenAreEmpty(): void
    {
        $this->assertSame([], $this->oneByteParser()->children());
    }

    public function testPartialUtf8RecognitionIsRejected(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Parser returned a partial UTF-8 code point');

        $this->oneByteParser()->matchPrefix(new Utf8Source("\u{e9}"));
    }

    public function testMarkedParserReportsEntryOffsetWithoutChangingFailureProgress(): void
    {
        $parser = new class extends Parser implements FailureDiagnosticAtStart {
            protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
            {
                return $this->failure($source, $byteOffset, 2);
            }

            public function __toString(): string
            {
                return 'predicate';
            }
        };
        $source = new ByteSource('xab');
        $session = new ParseSession($source);
        $match = $parser->recognizeInSession($source, 1, $session);

        $this->assertFalse($match->accepted);
        $this->assertSame(2, $match->length);
        $this->assertSame(1, $match->farthestFailure);
        $this->assertSame(1, $match->diagnostic->byteOffset);
    }

    public function testUnmarkedParserReportsObservedFailureProgress(): void
    {
        $parser = new class extends Parser {
            protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
            {
                return $this->failure($source, $byteOffset, 2);
            }

            public function __toString(): string
            {
                return 'consuming parser';
            }
        };
        $result = $parser->matchPrefix(new ByteSource('xab'), 1);

        $this->assertFalse($result->success);
        $this->assertSame(3, $result->diagnostic->byteOffset);
    }

    public function testMarkerPreservesExplicitDiagnostics(): void
    {
        $parser = new class extends Parser implements FailureDiagnosticAtStart {
            protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
            {
                $session->recordFailure($byteOffset + 1, 'explicit expectation');

                return $this->failure($source, $byteOffset, 2);
            }

            public function __toString(): string
            {
                return 'predicate';
            }
        };
        $result = $parser->matchPrefix(new ByteSource('xab'), 1);

        $this->assertSame(2, $result->diagnostic->byteOffset);
        $this->assertSame(['explicit expectation'], $result->diagnostic->expected);
    }

    private function oneByteParser(): Parser
    {
        return new class extends Parser {
            protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
            {
                return $this->success($source, $byteOffset, 1);
            }

            public function __toString(): string
            {
                return 'one byte';
            }
        };
    }
}
