<?php

declare(strict_types=1);

namespace Tests\Src\Directive;

use LogicException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Directive\UnaryDirective;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Source;

final class UnaryDirectiveTest extends TestCase
{
    public function testSnapshotDetachesTheOperandAndPreservesMetadata(): void
    {
        $child = new Text('a');
        $directive = $this->wrap($child)->name('wrapped');
        $copy = (new GraphSnapshot())->copy($directive);
        $child->guard(static fn(): bool => false);

        $this->assertFalse($directive->parseAll(new ByteSource('a'))->success);
        $result = $copy->parseAll(new ByteSource('a'));
        $this->assertTrue($result->success);
        $this->assertSame('wrapped', $result->node->name);
    }

    public function testForwardedNullabilityRejectsRepeatingAnEmptyOperand(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Nullable repetition');

        Repeat::star($this->wrap(new Nothing()))->freeze();
    }

    private function wrap(Parser $parser): UnaryDirective
    {
        return new class ($parser) extends UnaryDirective {
            public function __toString(): string
            {
                return (string) $this->getParser();
            }

            protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
            {
                return $this->getParser()->recognizeInSession($source, $byteOffset, $session);
            }
        };
    }
}
