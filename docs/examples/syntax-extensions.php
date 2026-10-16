<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Syntax\Extension\AsciiIdentifiers;
use Vanderlee\Comprehend\Syntax\SyntaxExtension;

$base = C::syntax();
$syntax = $base->extend(new AsciiIdentifiers())->extend(new class implements SyntaxExtension {
    public function factories(): iterable
    {
        yield 'semicolon' => static fn(): C => C::text(';');
    }
});
$declaration = $syntax->asciiKeyword('let')->ignoreThen($syntax->asciiIdentifier()->named('name'))
    ->thenIgnore($syntax->semicolon())->spacing(C::text(' ')->many());
$result = $declaration->parse('let name;');
if (!$result->success || $result->value() !== 'name' || $result->rawText() !== 'let name;') {
    throw new RuntimeException('Extension declaration values or source round trip failed');
}
if ($declaration->parse('letname;')->success || $declaration->parse('let na me;')->success) {
    throw new RuntimeException('Keyword boundary or identifier spacing changed');
}
if (in_array('asciiidentifier', $base->names(), true) || in_array('semicolon', $base->names(), true)) {
    throw new RuntimeException('Extensions modified the original vocabulary');
}
echo "Optional extensions, keyword boundaries and source round trips agree.\n";
