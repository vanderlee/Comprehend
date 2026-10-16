<?php

declare(strict_types=1);

namespace Tests\features\sources;

use LogicException;
use PHPUnit\Framework\TestCase;
use WeakReference;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Regex;
use Vanderlee\Comprehend\Parser\Terminal\Integer;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteFileSource;

uses(TestCase::class);

test('regexes share one session subject below the cap without changing context or logical work', function (
    string $text,
    bool $integer,
) {
    foreach ([0 => 2, 3 => 2, 4 => 1] as $cap => $reads) {
        $stream = tmpfile();
        fwrite($stream, $text);
        $source = new class ($stream) extends ByteFileSource {
            public int $fullReads = 0;

            public function sliceBytes(int $start, int $end): string
            {
                if ($start === 0 && $end === $this->byteLength()) {
                    ++$this->fullReads;
                }

                return parent::sliceBytes($start, $end);
            }
        };
        fclose($stream);
        try {
            $parser = new Sequence(new Regex('/ab/A'), $integer ? new Integer() : new Regex('/(?<=b)cd/A'));
            $work = $integer ? 13 : 11;
            $session = new ParseSession($source, limits: new ParseLimits(maxWork: $work, maxRegexSubjectBytes: $cap));
            $match = $parser->recognizeInSession($source, 0, $session);
            $this->assertTrue($match->accepted);
            $this->assertSame($work, $session->workUsed());
            $this->assertSame($reads, $source->fullReads);
            $result = new ParseResult($match);
            $weak = WeakReference::create($session);
            unset($session);
            $this->assertNull($weak->get());
            $this->assertSame($text, $result->rawText());

            $session = new ParseSession($source, limits: new ParseLimits(maxWork: 10, maxRegexSubjectBytes: $cap));
            try {
                $parser->recognizeInSession($source, 0, $session);
                $this->fail('Expected logical regex work limit');
            } catch (ParseLimitException $error) {
                $this->assertSame(2, $error->byteOffset);
                $this->assertSame(7, $session->workUsed());
            }
        } finally {
            $source->close();
        }
    }
})->with([['abcd', false], ['ab42', true]]);

test('a cached regex subject never bypasses explicit source closure', function () {
    $stream = tmpfile();
    fwrite($stream, 'abc');
    $source = new ByteFileSource($stream);
    fclose($stream);
    $session = new ParseSession($source);
    $this->assertSame('abc', $session->regexSubject());
    $source->close();
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('File source is closed');
    $session->regexSubject();
});
