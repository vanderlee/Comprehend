<?php

declare(strict_types=1);

namespace Tests\Src\Match;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Failure;
use Vanderlee\Comprehend\Result\Diagnostic;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;

uses(TestCase::class);

test('base record has no acceptance or semantic results', function (): void {
    $match = $this->getMockForAbstractClass(AbstractMatch::class, [2]);

    $this->assertFalse($match->accepted);
    $this->assertSame(2, $match->length);
    $this->assertSame([], $match->getResults());
    $this->assertSame('fallback', $match->getResult('missing', 'fallback'));
});

test('negative progress is rejected', function (): void {
    $this->expectException(InvalidArgumentException::class);

    $this->getMockForAbstractClass(AbstractMatch::class, [-1]);
});

test('failure metadata preserves interior byte positions and validated diagnostics', function (): void {
    $source = new Utf8Source("\u{e9}");
    $diagnostic = new Diagnostic($source, 1, [], []);
    $match = new Failure();
    $match->setSourceMetadata($source->span(0, 0), 1, $diagnostic);

    $this->assertSame(1, $match->farthestFailure);
    $this->assertSame($diagnostic, $match->diagnostic);
});

test('unrelated diagnostics cannot bypass failure position validation', function (bool $foreign): void {
    $source = new ByteSource('x');
    $diagnostic = new Diagnostic($foreign ? new ByteSource('xxx') : $source, $foreign ? 2 : 0, [], []);
    $match = new Failure();
    $this->expectException(InvalidArgumentException::class);

    $match->setSourceMetadata($source->span(0, 0), 2, $diagnostic);
})->with([true, false]);
