<?php

declare(strict_types=1);

namespace Tests\Feature\sources;

use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Extensions\Numbers\Parser\IntegerNumber;
use Vanderlee\Comprehend\Parser\Terminal\Regex;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteFileSource;
use Vanderlee\Comprehend\Source\Utf8FileSource;
use Vanderlee\Comprehend\Source\InMemorySource;
use Vanderlee\Comprehend\Source\SourceMode;
use Vanderlee\Comprehend\Source\Span;
use WeakReference;

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
            $parser = new Sequence(new Regex('/ab/A'), $integer ? new IntegerNumber() : new Regex('/(?<=b)cd/A'));
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

test('custom memory sources retain readability checks even with a cached subject', function (): void {
    $source = new class ('abc') extends InMemorySource {
        public bool $readable = true;
        public int $probes = 0;

        public function mode(): SourceMode
        {
            return SourceMode::BYTES;
        }

        public function readUnit(int $byteOffset): ?Span
        {
            $this->isUnitBoundary($byteOffset);

            return $byteOffset === $this->byteLength() ? null : $this->span($byteOffset, $byteOffset + 1);
        }

        public function isUnitBoundary(int $byteOffset): bool
        {
            ++$this->probes;
            if (!$this->readable) {
                throw new LogicException('Custom source is closed');
            }
            $this->assertByteOffset($byteOffset);

            return true;
        }
    };
    $session = new ParseSession($source);
    $this->assertSame('abc', $session->regexSubject());
    $this->assertSame('abc', $session->regexSubject());
    $this->assertSame(2, $source->probes);
    $source->readable = false;
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Custom source is closed');

    $session->regexSubject();
});

test('cached file subjects preserve the active page and reject closure', function (string $class): void {
    $bytes = str_repeat('a', 16383) . "\u{1f642}end";
    $stream = tmpfile();
    fwrite($stream, $bytes);
    $source = new $class($stream, 4);
    fclose($stream);
    try {
        $session = new ParseSession($source);
        $this->assertSame($bytes, $session->regexSubject());
        $source->sliceBytes(16383, 16387);
        // Observe cache retention directly: no timing threshold or public test hook is needed.
        $cacheStart = new ReflectionProperty(ByteFileSource::class, 'cacheStart');
        $activePage = $cacheStart->getValue($source);
        $this->assertGreaterThan(0, $activePage);
        $this->assertSame($bytes, $session->regexSubject());
        $this->assertSame($activePage, $cacheStart->getValue($source));
        $this->assertSame($bytes, $session->regexSubject());
        $this->assertSame($activePage, $cacheStart->getValue($source));
        $source->close();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('File source is closed');
        $session->regexSubject();
    } finally {
        $source->close();
    }
})->with([ByteFileSource::class, Utf8FileSource::class]);

test('custom file sources retain their boundary probe on every subject request', function (): void {
    $stream = tmpfile();
    fwrite($stream, 'abc');
    $source = new class ($stream) extends ByteFileSource {
        public int $probes = 0;
        public bool $readable = true;

        public function isUnitBoundary(int $byteOffset): bool
        {
            ++$this->probes;
            if (!$this->readable) {
                throw new LogicException('Custom file source is closed');
            }

            return parent::isUnitBoundary($byteOffset);
        }
    };
    fclose($stream);
    try {
        $session = new ParseSession($source);
        $this->assertSame('abc', $session->regexSubject());
        $this->assertSame('abc', $session->regexSubject());
        $this->assertSame(2, $source->probes);
        $source->readable = false;
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Custom file source is closed');
        $session->regexSubject();
    } finally {
        $source->close();
    }
});
