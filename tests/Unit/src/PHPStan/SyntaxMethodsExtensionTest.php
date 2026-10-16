<?php

declare(strict_types=1);

namespace Tests\Unit\src\PHPStan;

use PHPStan\Testing\PHPStanTestCase;
use Vanderlee\Comprehend\Comprehend;
use Vanderlee\Comprehend\PHPStan\SyntaxMethodsExtension;
use Vanderlee\Comprehend\Syntax;

uses(PHPStanTestCase::class);

test('syntax reflection exposes built in factories without inventing custom or fluent factories', function (): void {
    $provider = $this::createReflectionProvider();
    $extension = new SyntaxMethodsExtension($provider);
    $syntax = $provider->getClass(Syntax::class);
    $this->assertTrue($extension->hasMethod($syntax, 'integer'));
    $this->assertTrue($extension->hasMethod($syntax, 'optionalValue'));
    $this->assertFalse($extension->hasMethod($syntax, 'map'));
    $this->assertFalse($extension->hasMethod($syntax, 'customVocabularyRule'));
    $this->assertFalse($extension->hasMethod($provider->getClass(Comprehend::class), 'integer'));
    $this->assertSame('integer', $extension->getMethod($syntax, 'integer')->getName());
});
