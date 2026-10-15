<?php

declare(strict_types=1);

namespace Tests\Src\Directive;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Directive\Lexeme;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;

final class LexemeTest extends TestCase
{
    public function testSpacingIsSuppressedAndScopeIsRestoredAfterFailure(): void
    {
        $parser = new Lexeme((new Sequence(new Text('a'), new Text('b')))->spacing(new Text(' ')));
        $source = new ByteSource('a b');
        $session = new ParseSession($source);

        $this->assertFalse($parser->recognizeInSession($source, 0, $session)->accepted);
        $this->assertFalse($session->isLexeme());
        $this->assertTrue($parser->parseAll(new ByteSource('ab'))->success);
    }
}
