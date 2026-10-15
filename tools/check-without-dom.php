<?php

declare(strict_types=1);

// Run on a PHP build with DOM absent; PHPUnit itself requires DOM.
if (extension_loaded('dom') || class_exists('DOMDocument', false)) {
    throw new RuntimeException('This check must run without the DOM extension');
}

require __DIR__ . '/../vendor/autoload.php';

use Vanderlee\Comprehend\Comprehend as C;

$manifest = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true, flags: JSON_THROW_ON_ERROR);
if (isset($manifest['require']['ext-dom'])) {
    throw new RuntimeException('DOM must not be a runtime Composer requirement');
}

$grammar = C::text('id')->text(':')->then(C::integer(0, 255)->named('number'))->mapText('strtoupper');
$result = $grammar->parse('id : 42', spacing: C::text(' ')->many());
if (!$result->ok || $result->output() !== 'ID : 42' || $result->rawText() !== 'id : 42') {
    throw new RuntimeException('Parsing and mapped values must work without DOM');
}
if ($grammar->parse('invalid')->ok) {
    throw new RuntimeException('Syntax rejection must work without DOM');
}
foreach ([$result->node, $result->ast()] as $node) {
    $data = $node->toArray();
    $json = json_decode($node->toJson(), true, flags: JSON_THROW_ON_ERROR);
    if ($json !== $data || $data['text'] !== $result->rawText()) {
        throw new RuntimeException('AST/CST array and JSON output must work without DOM');
    }
    try {
        $node->toXml();
        throw new RuntimeException('XML output must reject unavailable DOM');
    } catch (LogicException $error) {
        if ($error->getMessage() !== 'XML serialization requires the ext-dom PHP extension') {
            throw $error;
        }
    }
}
echo "Parsing, values, AST/CST JSON and XML dependency errors passed without DOM.\n";
