<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Syntax\SyntaxExtension;

$base = C::syntax();
$syntax = $base->extend(new class implements SyntaxExtension {
    public function rules(): iterable
    {
        yield 'semicolon' => static fn(): C => C::text(';');
    }

    public function fluents(): iterable
    {
        return [];
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
if (!in_array('asciiidentifier', $base->names(), true) || in_array('semicolon', $base->names(), true)) {
    throw new RuntimeException('Extensions modified the original vocabulary');
}
$delimiters = $syntax->extend(new class implements SyntaxExtension {
    public function rules(): iterable
    {
        return [];
    }

    public function fluents(): iterable
    {
        yield 'parenthesized' => static fn(C $item): C => $item->between('(', ')');
    }
});
$wrapped = $delimiters->asciiIdentifier()->parenthesized()->parse('(name)');
if (!$wrapped->success || $wrapped->value() !== 'name' || $wrapped->rawText() !== '(name)') {
    throw new RuntimeException('Fluent extension transformation failed');
}
echo "Optional extensions, keyword boundaries and source round trips agree.\n";
