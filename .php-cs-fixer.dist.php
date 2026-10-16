<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/extensions',
        __DIR__ . '/tests',
        __DIR__ . '/tools',
        __DIR__ . '/docs/examples',
    ])
    ->append([__FILE__]);

return (new PhpCsFixer\Config())
    ->setRules(['@PER-CS' => true])
    ->setCacheFile(__DIR__ . '/build/php-cs-fixer.cache')
    ->setFinder($finder);
