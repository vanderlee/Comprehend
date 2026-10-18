<?php

declare(strict_types=1);

namespace Tests\Extensions\Abnf;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Extensions\Abnf\Reader;
use Vanderlee\Comprehend\Extensions\Abnf\SyntaxException;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\SourceMode;

uses(TestCase::class);

test('incremental definitions extend the supplied ruleset', function (): void {
    $rules = new Ruleset();
    $reader = new Reader("token = %s\"a\"\ntoken =/ %s\"b\"", $rules, SourceMode::BYTES);
    $reader->readRules();

    $this->assertTrue($rules->ref('token')->parseAll(new ByteSource('a'))->success);
    $this->assertTrue($rules->ref('token')->parseAll(new ByteSource('b'))->success);
    $this->assertFalse($rules->ref('token')->parseAll(new ByteSource('B'))->success);
});

test('undefined references are rejected after reading', function (): void {
    $reader = new Reader('token = missing', new Ruleset(), SourceMode::BYTES);
    $this->expectException(SyntaxException::class);
    $this->expectExceptionMessage('Undefined rule: missing');

    $reader->readRules();
});
