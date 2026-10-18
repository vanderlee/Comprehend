<?php

declare(strict_types=1);

namespace Tests\Src\Library;

use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Library\Rfc2046;
use Vanderlee\Comprehend\Extensions\Library\Rfc8259;
use Vanderlee\Comprehend\Extensions\Library\Xml;
use Vanderlee\Comprehend\Extensions\Library\Yaml;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;

test('specification overrides are isolated from frozen grammars', function (): void {
    $json = new Rfc8259();
    $frozen = $json->freeze();
    $json->define('number', C::text('custom'));
    expect($json->freeze()->parse('[custom]')->success)->toBeTrue();
    expect($frozen->parse('[custom]')->success)->toBeFalse();
    expect($frozen->parse('[42]')->success)->toBeTrue();
    expect((new Rfc8259())->freeze()->parse('[custom]')->success)->toBeFalse();
});

test('YAML block recognition honors nonzero byte offsets and Unicode sources', function (): void {
    $input = "- key: é\n  value: 2\n";
    $grammar = (new Yaml())->freeze();
    foreach ([new ByteSource('xx' . $input), new Utf8Source('xx' . $input)] as $source) {
        $result = $grammar->parse($source, 2);
        expect($result->success)->toBeTrue();
        expect($result->rawText())->toBe($input);
        expect($result->consumedByteLength)->toBe(strlen($input));
        expect($result->start)->toBe(2);
        expect($result->end)->toBe(2 + strlen($input));
    }
});

test('YAML indentation recursion participates in shared parsing limits', function (): void {
    $grammar = (new Yaml())->freeze();
    $input = '';
    for ($depth = 0; $depth < 25; $depth++) {
        $input .= str_repeat('  ', $depth) . "key:\n";
    }
    expect(fn() => $grammar->parse($input, limits: new ParseLimits(maxDepth: 12)))
        ->toThrow(ParseLimitException::class);
    expect(fn() => $grammar->parse($input, limits: new ParseLimits(maxWork: 20)))
        ->toThrow(ParseLimitException::class);
    expect($grammar->parse("key: value\n")->success)->toBeTrue();
});

test('XML recognition keeps commit effects explicit', function (): void {
    $calls = 0;
    $grammar = (new Xml())->rule('document')->onCommit(static function () use (&$calls): void {
        $calls++;
    })->freeze();
    $result = $grammar->parse('<root><child/></root>');
    expect($result->success)->toBeTrue();
    expect($calls)->toBe(0);
    expect($result->commit())->toBeTrue();
    expect($result->commit())->toBeTrue();
    expect($calls)->toBe(1);
});

test('MIME multipart imports require and honor their declared boundary', function (): void {
    $body = "preamble\r\n--example\r\nContent-Type: text/plain\r\n\r\nHello\r\n--example--\r\nepilogue";
    $mime = new Rfc2046();
    expect($mime->rule('multipart_body', 'example')->parse($body)->success)->toBeTrue();
    expect($mime->rule('multipart_body', 'different')->parse($body)->success)->toBeFalse();
});
