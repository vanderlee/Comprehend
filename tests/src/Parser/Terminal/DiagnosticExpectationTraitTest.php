<?php

declare(strict_types=1);

namespace Tests\Src\Parser\Terminal;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Terminal\DiagnosticExpectationTrait;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Source;

final class DiagnosticExpectationTraitTest extends TestCase
{
    public function testStringExpectationKeepsNamedRulePath(): void
    {
        $parser = new class extends Parser {
            use DiagnosticExpectationTrait;

            protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
            {
                return $this->failure($source, $byteOffset);
            }

            public function __toString(): string
            {
                return 'expected unit';
            }
        };
        $parser->diagnosticRule('custom');
        $result = $parser->matchPrefix(new ByteSource('x'), 1);

        $this->assertFalse($result->success);
        $this->assertSame(1, $result->diagnostic->byteOffset);
        $this->assertSame(['expected unit'], $result->diagnostic->expected);
        $this->assertSame([['custom']], $result->diagnostic->rulePaths);
    }

    public function testNamespaceDoesNotOptParserIntoStringExpectations(): void
    {
        $parser = new class extends Parser {
            protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
            {
                return $this->failure($source, $byteOffset);
            }

            public function __toString(): string
            {
                return 'unused description';
            }
        };
        $parser->diagnosticRule('named rule');
        $result = $parser->matchPrefix(new ByteSource(''));

        $this->assertSame(['named rule'], $result->diagnostic->expected);
    }
}
