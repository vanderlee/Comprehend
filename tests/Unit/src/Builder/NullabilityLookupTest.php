<?php

declare(strict_types=1);

namespace Tests\Unit\src\Builder;

use LogicException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\NullabilityLookup;
use Vanderlee\Comprehend\Parser\Terminal\Text;

abstract class NullabilityLookupTest extends TestCase
{
    /** @return iterable<string, array{bool|null, bool|null, bool|null, bool|null}> */
    public static function combinations(): iterable
    {
        yield 'false false' => [false, false, false, false];
        yield 'false unknown' => [false, null, false, null];
        yield 'false true' => [false, true, false, true];
        yield 'unknown false' => [null, false, false, null];
        yield 'unknown unknown' => [null, null, null, null];
        yield 'unknown true' => [null, true, null, true];
        yield 'true false' => [true, false, false, true];
        yield 'true unknown' => [true, null, null, true];
        yield 'true true' => [true, true, true, true];
    }
}

uses(NullabilityLookupTest::class);

test('combines three valued estimates', function (?bool $left, ?bool $right, ?bool $every, ?bool $any): void {
    $a = new Text('a');
    $b = new Text('b');
    $lookup = new NullabilityLookup([spl_object_id($a) => $left, spl_object_id($b) => $right]);

    $this->assertSame($left, $lookup->get($a));
    $this->assertSame($every, $lookup->every([$a, $b]));
    $this->assertSame($any, $lookup->any([$a, $b]));
})->with(NullabilityLookupTest::combinations());

test('empty operand identities', function (): void {
    $lookup = new NullabilityLookup([]);

    $this->assertTrue($lookup->every([]));
    $this->assertFalse($lookup->any([]));
});

test('rejects undiscovered operands', function (): void {
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('outside the collected graph');

    (new NullabilityLookup([]))->get(new Text('a'));
});
