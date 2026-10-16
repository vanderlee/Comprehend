<?php

declare(strict_types=1);

/** Phase 2A gate: syntax and the absence of removed public/runtime surfaces. */
require_once __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$errors = [];
if (is_file($root . '/src/Library/functions.php')) {
    $errors[] = 'Removed Library helper file is present';
}
foreach (['s', 'c', 'star', 'plus', 'opt', 'repeat', 'text', 'char', 'set', 'range', 'regex'] as $function) {
    if (function_exists('Vanderlee\\Comprehend\\Library\\' . $function)) {
        $errors[] = 'Removed Library function: ' . $function;
    }
}

foreach (['Compatibility', 'Core'] as $directory) {
    $path = $root . '/src/' . $directory;
    if (is_dir($path)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile()) {
                $errors[] = 'Removed namespace contains a file: ' . $file->getPathname();
            }
        }
    }
}
foreach (['match', 'matchSource', 'parseSource', 'matchUnresolved', 'matchSourceUnresolved', 'matchAsciiUnresolved',
    'matchAllSourceUnresolved', 'supportsSource', 'parseInContext', 'parse', 'callback', 'token', '__invoke'] as $method) {
    if (method_exists(Vanderlee\Comprehend\Parser\Parser::class, $method)) {
        $errors[] = 'Removed Parser API: ' . $method;
    }
}
foreach ([Vanderlee\Comprehend\Builder\Ruleset::class, Vanderlee\Comprehend\Builder\Definition::class,
    Vanderlee\Comprehend\Builder\Implementation::class, Vanderlee\Comprehend\Parser\Stub::class] as $class) {
    foreach (['__get', '__set', '__call', '__callStatic', '__invoke'] as $method) {
        if (method_exists($class, $method)) {
            $errors[] = 'Removed magic adapter: ' . $class . '::' . $method;
        }
    }
}
foreach (['src', 'extensions', 'tests', 'docs/examples', 'tools'] as $directory) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $code = file_get_contents($file->getPathname());
        try {
            token_get_all($code, TOKEN_PARSE);
        } catch (ParseError $error) {
            $errors[] = $file->getPathname() . ': ' . $error->getMessage();
        }
        if ($directory !== 'src') {
            continue;
        }
        if (preg_match('/Legacy|Compatibility\\\\|ArgumentsTrait|supportsSource|tokenTransparent|tokenChildIndexes|usesLegacySemantics|allowsLegacyAscii/', $code)) {
            $errors[] = 'Compatibility scaffolding in ' . $file->getPathname();
        }
    }
}
if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}
echo 'Canonical API and PHP syntax gate passed.' . PHP_EOL;
