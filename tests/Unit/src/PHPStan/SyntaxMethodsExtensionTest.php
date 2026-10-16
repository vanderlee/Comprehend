<?php

declare(strict_types=1);

namespace Tests\Unit\src\PHPStan;

use PHPStan\Testing\PHPStanTestCase;
use Vanderlee\Comprehend\Comprehend;
use Vanderlee\Comprehend\PHPStan\SyntaxMethodsExtension;
use Vanderlee\Comprehend\Syntax;

uses(PHPStanTestCase::class);

// PHPStan and the test runner bundle different PhpParser versions; isolate the analyzer container.

test('syntax reflection exposes built in factories without inventing custom or fluent factories', function (): void {
    $provider = $this::createReflectionProvider();
    $extension = new SyntaxMethodsExtension($provider);
    $syntax = $provider->getClass(Syntax::class);
    $this->assertTrue($extension->hasMethod($syntax, 'integer'));
    $this->assertFalse($extension->hasMethod($syntax, 'optionalValue'));
    $this->assertFalse($extension->hasMethod($syntax, 'map'));
    $this->assertFalse($extension->hasMethod($syntax, 'customVocabularyRule'));
    $this->assertFalse($extension->hasMethod($provider->getClass(Comprehend::class), 'integer'));
    $this->assertSame('integer', $extension->getMethod($syntax, 'integer')->getName());
})->setRunTestInSeparateProcess(true)->setPreserveGlobalState(false);
