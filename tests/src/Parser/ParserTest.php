<?php

declare(strict_types=1);

namespace Tests\Src\Parser;

use LogicException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Match\AbstractMatch;
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

    public function testPartialUtf8RecognitionIsRejected(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Parser returned a partial UTF-8 code point');

        $this->oneByteParser()->matchPrefix(new Utf8Source("\u{e9}"));
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
