<?php

declare(strict_types=1);

namespace Tests\Src\Library;

use Tests\Fixtures\SpecificationGrammars;
use Vanderlee\Comprehend\Library\Rfc9535;
use Vanderlee\Comprehend\Library\Rfc9651;
use Vanderlee\Comprehend\Library\Yaml;
use Vanderlee\Comprehend\Source\ByteSource;

test('upstream specification syntax acceptance', function (
    string $specification,
    string $rule,
    string $input,
    bool $accepted,
    bool $canFail,
): void {
    $result = SpecificationGrammars::get($specification, $rule)->parse(new ByteSource($input));
    if (!$result->success && $canFail) {
        expect($canFail)->toBeTrue();

        return;
    }
    expect($result->success)->toBe($accepted);
    if ($accepted) {
        expect($result->rawText())->toBe($input);
        expect($result->consumedByteLength)->toBe(strlen($input));
    }
})->with(function (): iterable {
    $sources = [
        'yaml' => [Yaml::class, 'stream'],
        'structured-fields' => [Rfc9651::class, 'sf_list'],
        'jsonpath' => [Rfc9535::class, 'jsonpath_query'],
    ];
    foreach ($sources as $file => [$class, $root]) {
        $path = __DIR__ . '/../../Fixtures/Specifications/' . $file . '.json';
        $cases = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        foreach ($cases as $case) {
            yield $file . ': ' . $case['name'] => [
                $class,
                $case['rule'] ?? $root,
                base64_decode($case['input_base64'], true),
                $case['accepted'],
                $case['can_fail'] ?? false,
            ];
        }
    }
})->group('library', 'specifications', 'conformance');
