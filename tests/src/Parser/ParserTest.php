<?php

declare(strict_types=1);

namespace Tests\Src\Parser;

use LogicException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\FailureDiagnosticAtStart;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\Utf8Source;

abstract class ParserTest extends TestCase
{
    protected function oneByteParser(): Parser
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

uses(ParserTest::class);

test('explicit diagnostic names take precedence after the class label has been used', function () {
    $parser = new Text('a');
    $this->assertSame('Text', $parser->graphLabel());
    $parser->diagnosticRule('letter');
    $this->assertSame('letter', $parser->graphLabel());
    $result = $parser->parseAll(new ByteSource('b'));
    $this->assertSame([['letter']], $result->diagnostic->rulePaths);
});

test('base runtime binds custom recognition to the source', function (): void {
    $parser = $this->oneByteParser();
    $source = new ByteSource('ab');
    $result = $parser->matchPrefix($source, 1);

    $this->assertTrue($result->success);
    $this->assertSame('b', $result->rawText());
    $this->assertSame($source, $result->span->source);
    $this->assertSame([1, 2], [$result->start, $result->end]);
});

test('default children are empty', function (): void {
    $this->assertSame([], $this->oneByteParser()->children());
});

test('partial utf8 recognition is rejected', function (): void {
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Parser returned a partial UTF-8 code point');

    $this->oneByteParser()->matchPrefix(new Utf8Source("\u{e9}"));
});

test('marked parser reports entry offset without changing failure progress', function (): void {
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
});

test('unmarked parser reports observed failure progress', function (): void {
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
});

test('marker preserves explicit diagnostics', function (): void {
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
});
