<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Vanderlee\Comprehend\Comprehend;
use Vanderlee\Comprehend\Syntax\Catalog;
use Vanderlee\Comprehend\Extensions\Extraction\Extraction;
use Vanderlee\Comprehend\Extensions\Numbers\Numbers;
use Vanderlee\Comprehend\Extensions\Optimizer\GraphOptimizer;
use Vanderlee\Comprehend\Syntax\SpecificationExtension;

// Mirror factory names and signatures without loading PHPStan or running its extensions.
$root = dirname(__DIR__);
$specificationFactories = [];
$specificationMethods = [];
$manifestPaths = array_merge(
    glob($root . '/extensions/*.extension.json'),
    glob($root . '/extensions/*/*.extension.json'),
);
sort($manifestPaths, SORT_STRING);
foreach ($manifestPaths as $manifestPath) {
    $manifest = json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
    $class = $manifest['class'] ?? null;
    if (!is_string($class) || !is_subclass_of($class, SpecificationExtension::class)) {
        continue;
    }
    foreach ((new $class())->rules() as $name => $factory) {
        $specificationFactories[$name] = $factory;
        $parameters = [];
        foreach ((new ReflectionFunction($factory))->getParameters() as $parameter) {
            $parameters[] = $parameter->getType() . ' $' . $parameter->getName()
                . ' = ' . var_export($parameter->getDefaultValue(), true);
        }
        $specificationMethods[] = ' * @method static self<string> ' . $name . '(' . implode(', ', $parameters) . ')';
    }
}
$facadePath = $root . '/src/Comprehend.php';
$facadeOriginal = str_replace("\r\n", "\n", file_get_contents($facadePath));
$facade = preg_replace(
    '/ \* BEGIN GENERATED SPECIFICATIONS.*? \* END GENERATED SPECIFICATIONS\n/s',
    '',
    $facadeOriginal,
);
$facade = str_replace(
    ' * @method static self<string> text(string $text)',
    " * BEGIN GENERATED SPECIFICATIONS (tools/generate-syntax-phpdoc.php)\n"
    . implode("\n", $specificationMethods) . "\n * END GENERATED SPECIFICATIONS\n"
    . ' * @method static self<string> text(string $text)',
    $facade,
);
$methods = [];
preg_match_all('/@method static (.+) (\w+)\((.*)\)/', $facade, $matches, PREG_SET_ORDER);
foreach ($matches as $match) {
    $methods[strtolower($match[2])] = str_replace(
        'self',
        'Comprehend',
        $match[1] . ' ' . $match[2] . '(' . $match[3] . ')',
    );
}
$lines = [];
$extensionFactories = [
    ...iterator_to_array((new Numbers())->rules()),
    ...iterator_to_array((new GraphOptimizer())->rules()),
    ...$specificationFactories,
];
$factoryNames = array_values(array_unique([
    ...Catalog::builtinNames(),
    ...array_keys($extensionFactories),
]));
foreach ($factoryNames as $name) {
    if (Catalog::builtinRule($name) !== null || isset($extensionFactories[$name])) {
        $method = $methods[strtolower($name)] ?? null;
        if ($method === null) {
            throw new RuntimeException('Add a Comprehend factory signature for ' . $name);
        }
        $lines[] = ' * @method ' . $method;
    }
}
foreach ((new Extraction())->rules() as $name => $factory) {
    $method = ['valuechoice' => 'valueChoice', 'repeatvalues' => 'repeatValues', 'manyvalues' => 'manyValues',
        'somevalues' => 'someValues', 'optionalvalue' => 'optionalValue'][$name] ?? $name;
    $type = match ($name) {
        'record' => 'array<array-key, mixed>',
        'collect' => 'list<mixed>',
        'repeatvalues', 'manyvalues', 'somevalues' => 'list<mixed>',
        default => 'mixed',
    };
    $parameters = [];
    foreach ((new ReflectionFunction($factory))->getParameters() as $parameter) {
        $native = $parameter->getType();
        $types = $native instanceof ReflectionUnionType ? $native->getTypes() : [$native];
        $typeName = implode('|', array_map(static function (ReflectionNamedType $type): string {
            $name = $type->isBuiltin() ? $type->getName() : '\\' . $type->getName();
            if ($name === 'array') {
                return 'array<array-key, Comprehend|Parser|string>';
            }
            return ($type->allowsNull() && !in_array($name, ['mixed', 'null'], true) ? '?' : '') . $name;
        }, $types));
        $typeName = str_replace(
            ['\\Vanderlee\\Comprehend\\Comprehend', '\\Vanderlee\\Comprehend\\Parser\\Parser'],
            ['Comprehend', 'Parser'],
            $typeName,
        );
        $default = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
        $parameters[] = $typeName . ' ' . ($parameter->isVariadic() ? '...' : '') . '$' . $parameter->getName()
            . ($parameter->isDefaultValueAvailable()
                ? ' = ' . ($default === null ? 'null' : var_export($default, true))
                : '');
    }
    $lines[] = ' * @method Comprehend<' . $type . '> ' . $method . '(' . implode(', ', $parameters) . ')';
}
$path = $root . '/src/Syntax.php';
$source = file_get_contents($path);
$source = str_replace("\r\n", "\n", $source);
$source = preg_replace('/ \* BEGIN GENERATED FACTORIES.*? \* END GENERATED FACTORIES\n/s', '', $source);
$source = str_replace(
    ' * authoring calls through Catalog to Comprehend so extensions use the same parser runtime.',
    " * authoring calls through Catalog to Comprehend so extensions use the same parser runtime.\n"
    . " * BEGIN GENERATED FACTORIES (tools/generate-syntax-phpdoc.php)\n" . implode("\n", $lines)
    . "\n * END GENERATED FACTORIES",
    $source,
);
if (in_array('--check', $argv, true)) {
    if (str_replace("\r\n", "\n", file_get_contents($path)) !== $source || $facadeOriginal !== $facade) {
        fwrite(STDERR, "Factory PHPDoc is stale; run php tools/generate-syntax-phpdoc.php\n");
        exit(1);
    }
} else {
    file_put_contents($facadePath, $facade);
    file_put_contents($path, $source);
}
