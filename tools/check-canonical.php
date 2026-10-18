<?php

declare(strict_types=1);

/** Phase 2A gate: syntax and the absence of removed public/runtime surfaces. */
require_once __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$errors = [];
foreach ([
    'Vanderlee\\Comprehend\\Extensions\\Shared\\AbstractSpecification',
    'Vanderlee\\Comprehend\\Extensions\\Shared\\SpecificationExtension',
] as $removedClass) {
    if (class_exists($removedClass)) {
        $errors[] = 'Removed shared authoring class is present: ' . $removedClass;
    }
}
if (method_exists(Vanderlee\Comprehend\Syntax\ExtensionLoader::class, 'optimizer')) {
    $errors[] = 'Removed optimizer-specific extension lookup is present';
}
foreach (
    [
        'Vanderlee\\Comprehend\\Builder\\GraphOptimizer',
        'Vanderlee\\Comprehend\\Runtime\\LexicalTable',
        'Vanderlee\\Comprehend\\Abnf\\Compiler',
        'Vanderlee\\Comprehend\\Abnf\\Reader',
        'Vanderlee\\Comprehend\\Abnf\\SyntaxException',
    ] as $removedClass
) {
    if (class_exists($removedClass)) {
        $errors[] = 'Removed core class is present: ' . $removedClass;
    }
}
foreach (['Repeat', 'Choice', 'Not'] as $combinator) {
    $path = $root . '/src/Parser/Structure/' . $combinator . '.php';
    $source = file_get_contents($path);
    if (preg_match('/Optimizer|LexicalTable|ReadRun|prepareLexicalRecognition|withOptimizedRecognition/', $source)) {
        $errors[] = 'Core combinator contains optimizer dependencies: ' . $combinator;
    }
}
foreach (['ReadRun', 'ReadWhile', 'ReadUntil', 'ReadWhileSet', 'ReadWhileRange', 'ReadRest'] as $scanner) {
    if (class_exists('Vanderlee\\Comprehend\\Parser\\Terminal\\' . $scanner)) {
        $errors[] = 'Removed core scanner class is present: ' . $scanner;
    }
}
foreach (
    [
        'readWhile', 'readUntil', 'readWhileSet', 'readWhileRange',
        'readRest', 'skipWhile', 'skipUntil', 'skipRest',
    ] as $factory
) {
    if (
        Vanderlee\Comprehend\Syntax\Catalog::builtinRule($factory) !== null
        || in_array(strtolower($factory), Vanderlee\Comprehend\Syntax\Catalog::builtinNames(), true)
    ) {
        $errors[] = 'Removed core scanner factory is present: ' . $factory;
    }
}
if (class_exists('Vanderlee\\Comprehend\\Parser\\Terminal\\Integer')) {
    $errors[] = 'Removed core Integer parser is present';
}
foreach ([
    'Vanderlee\\Comprehend\\Extensions\\Integers',
    'Vanderlee\\Comprehend\\Extensions\\Integers\\Parser\\Integer',
    'Vanderlee\\Comprehend\\Extensions\\Numbers\\Parser\\Integer',
] as $removedClass) {
    if (class_exists($removedClass)) {
        $errors[] = 'Removed numeric class is present: ' . $removedClass;
    }
}
if (is_file($root . '/src/Library/functions.php')) {
    $errors[] = 'Removed Library helper file is present';
}
$removedLibraryClasses = [
    'AbstractSpecification',
    'Internal\\UnicodeEscapes',
    'Internal\\Utf8NonAscii',
    'Internal\\YamlBlock',
    'Internal\\YamlCharacters',
    'Rfc1034',
    'Rfc1035',
    'Rfc1123',
    'Rfc2045',
    'Rfc2046',
    'Rfc2234',
    'Rfc3339',
    'Rfc3513',
    'Rfc3986',
    'Rfc3987',
    'Rfc4234',
    'Rfc4291',
    'Rfc4408',
    'Rfc4648',
    'Rfc5234',
    'Rfc5321',
    'Rfc5322',
    'Rfc5646',
    'Rfc6376',
    'Rfc6531',
    'Rfc6532',
    'Rfc6570',
    'Rfc6854',
    'Rfc6901',
    'Rfc7208',
    'Rfc7405',
    'Rfc8141',
    'Rfc8259',
    'Rfc9535',
    'Rfc9557',
    'Rfc9562',
    'Rfc9651',
    'Rfc9989',
    'Rfc9990',
    'Rfc9991',
    'SemVer',
    'SpecificationExtension',
    'Xml',
    'Yaml',
];
foreach ($removedLibraryClasses as $relative) {
    foreach (['Vanderlee\\Comprehend\\Library\\', 'Vanderlee\\Comprehend\\Extensions\\Library\\'] as $namespace) {
        if (class_exists($namespace . $relative)) {
            $errors[] = 'Removed Library class is present: ' . $namespace . $relative;
        }
    }
}
$composer = json_decode(file_get_contents($root . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
if (isset($composer['autoload']['psr-4']['Vanderlee\\Comprehend\\Library\\'])) {
    $errors[] = 'Removed Library namespace mapping is present';
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
