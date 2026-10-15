<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Abnf;

use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Range;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\SourceMode;
use Vanderlee\Comprehend\Tree\ConcreteNode;

/** Recursive descent for authoring syntax only; emitted parsers share one runtime. */
final class Reader
{
    private int $position = 0;
    private readonly ByteSource $source;
    /**
     * @var array<string, true>
     */
    private array $definitions = [];
    /**
     * @var array<string, int>
     */
    private array $references = [];

    public function __construct(
        private readonly string $text,
        private readonly Ruleset $rules,
        private readonly SourceMode $encoding,
    ) {
        $this->source = new ByteSource($text);
        if (preg_match('/[^\x09\x0A\x0D\x20-\x7E]/', $text, $invalid, PREG_OFFSET_CAPTURE) === 1) {
            $this->fail('Grammar text must be ASCII; use numeric values for encoded terminals', $invalid[0][1]);
        }
        if (preg_match('/\r(?!\n)/', $text, $invalid, PREG_OFFSET_CAPTURE) === 1) {
            $this->fail('Use LF or CRLF line endings', $invalid[0][1]);
        }
    }

    public function readRules(): void
    {
        while ($this->position < strlen($this->text)) {
            $this->skipRuleSeparators();
            if ($this->position === strlen($this->text)) {
                break;
            }

            $this->readRule();
        }

        foreach ($this->references as $name => $offset) {
            if (!$this->rules->defined($name)) {
                $this->fail('Undefined rule: ' . $name, $offset);
            }
        }
    }

    private function readRule(): void
    {
        $start = $this->position;
        $name = $this->ruleName();
        $this->skipWhitespace();
        $this->expect('=');

        $incremental = $this->peek() === '/';
        if ($incremental) {
            ++$this->position;
            if (!$this->rules->defined($name)) {
                $this->fail('Incremental rule needs an existing definition: ' . $name, $start);
            }
        } elseif (isset($this->definitions[$name])) {
            $this->fail('Duplicate rule; use =/ to extend it: ' . $name, $start);
        }

        $this->skipWhitespace();
        $expression = $this->alternation();
        $this->skipWhitespace();
        if ($this->peek() !== '' && $this->peek() !== "\r" && $this->peek() !== "\n") {
            $this->fail('Expected end of rule');
        }

        if ($incremental) {
            $expression = new Choice($this->rules->resolveRule($name), $expression);
        }
        $this->definitions[$name] = true;
        $this->rules->define($name, $expression);
    }

    private function fail(string $message, ?int $offset = null): never
    {
        throw new SyntaxException($message, $this->source, min($offset ?? $this->position, strlen($this->text)));
    }

    private function skipRuleSeparators(): void
    {
        while (true) {
            $start = $this->position;
            $this->take('/[ \t\r\n]+/A');
            $this->take('/;[^\r\n]*/A');
            if ($this->position === $start) {
                return;
            }
        }
    }

    private function take(string $pattern): string
    {
        if (preg_match($pattern, $this->text, $match, 0, $this->position) !== 1) {
            return '';
        }
        $this->position += strlen($match[0]);

        return $match[0];
    }

    private function ruleName(): string
    {
        $name = $this->take('/[A-Za-z][A-Za-z0-9-]*/A');
        if ($name === '') {
            $this->fail('Expected rule name or supported element');
        }

        return strtolower($name);
    }

    /** Spaces, comments and indented continuation lines; an unindented newline ends a rule. */
    private function skipWhitespace(): bool
    {
        $start = $this->position;
        while (true) {
            $this->take('/[ \t]+/A');
            if ($this->peek() === ';') {
                $this->take('/;[^\r\n]*/A');
            }
            $newline = $this->peek() === "\r" ? "\r\n" : "\n";
            if (
                substr($this->text, $this->position, strlen($newline)) === $newline
                && in_array($this->text[$this->position + strlen($newline)] ?? '', [' ', "\t"], true)
            ) {
                $this->position += strlen($newline);
                continue;
            }
            break;
        }

        return $this->position > $start;
    }

    private function peek(): string
    {
        return $this->text[$this->position] ?? '';
    }

    private function expect(string $character): void
    {
        if ($this->peek() !== $character) {
            $this->fail('Expected ' . $character);
        }
        ++$this->position;
    }

    private function alternation(): Parser
    {
        $branches = [$this->concatenation()];
        while ($this->peek() === '/') {
            ++$this->position;
            $this->skipWhitespace();
            $branches[] = $this->concatenation();
        }

        return count($branches) === 1 ? $branches[0] : new Choice(...$branches);
    }

    private function concatenation(): Parser
    {
        $elements = [$this->repetition()];
        while (true) {
            $separated = $this->skipWhitespace();
            if (in_array($this->peek(), ['', '/', ')', ']', "\r", "\n"], true)) {
                break;
            }
            if (!$separated) {
                $this->fail('Concatenated elements require whitespace');
            }
            $elements[] = $this->repetition();
        }

        return count($elements) === 1 ? $elements[0] : new Sequence(...$elements);
    }

    private function repetition(): Parser
    {
        $digits = $this->take('/[0-9]+/A');
        $minimum = $digits === '' ? 0 : $this->repetitionCount($digits);
        if ($this->peek() === '*') {
            ++$this->position;
            $upper = $this->take('/[0-9]+/A');
            $maximum = $upper === '' ? null : $this->repetitionCount($upper);
            if ($maximum !== null && $maximum < $minimum) {
                $this->fail('Repetition maximum is smaller than its minimum');
            }

            return new Repeat($this->element(), $minimum, $maximum);
        }

        $element = $this->element();

        return $digits === '' ? $element : Repeat::exact($element, $minimum);
    }

    private function repetitionCount(string $digits): int
    {
        $digits = ltrim($digits, '0');
        $maximum = (string) PHP_INT_MAX;
        if (
            strlen($digits) > strlen($maximum)
            || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0)
        ) {
            $this->fail('Repetition count exceeds PHP integer range');
        }

        return $digits === '' ? 0 : (int) $digits;
    }

    private function element(): Parser
    {
        $character = $this->peek();
        if ($character === '(' || $character === '[') {
            ++$this->position;
            $this->skipWhitespace();
            $expression = $this->alternation();
            $this->expect($character === '(' ? ')' : ']');

            return $character === '[' ? Repeat::optional($expression) : $expression;
        }
        if ($character === '"') {
            return $this->quoted(false);
        }
        if ($character === '%') {
            ++$this->position;
            $prefix = strtolower($this->peek());
            ++$this->position;
            if ($prefix === 's' || $prefix === 'i') {
                return $this->quoted($prefix === 's');
            }
            if (in_array($prefix, ['x', 'd', 'b'], true)) {
                return $this->numeric($prefix);
            }
            $this->fail('Unsupported percent value');
        }
        if ($character === '<') {
            $this->fail('Prose values are unsupported');
        }

        $start = $this->position;
        $name = $this->ruleName();
        $this->references[$name] ??= $start;

        return $this->rules->ref($name);
    }

    private function quoted(bool $sensitive): Parser
    {
        $this->expect('"');
        $start = $this->position;
        $end = strpos($this->text, '"', $start);
        if ($end === false || preg_match('/[\r\n]/', substr($this->text, $start, $end - $start)) === 1) {
            $this->fail('Unterminated quoted value', $start);
        }
        $literal = substr($this->text, $start, $end - $start);
        if (preg_match('/[\x00-\x1F\x7F]/', $literal, $invalid, PREG_OFFSET_CAPTURE) === 1) {
            $this->fail('Quoted values must contain printable ASCII', $start + $invalid[0][1]);
        }
        $this->position = $end + 1;

        if ($literal === '') {
            return new Nothing();
        }
        $parser = (new Text($literal))->setCaseSensitivity($sensitive);
        if (!$sensitive) {
            // ABNF folds ASCII only; Text also supports Unicode case equivalents.
            $parser->guard(
                static fn(ConcreteNode $node): bool => preg_match('/^[\x00-\x7F]*$/D', $node->rawText()) === 1,
            );
        }

        return $parser;
    }

    /** @param 'b'|'d'|'x' $prefix */
    private function numeric(string $prefix): Parser
    {
        [$base, $pattern] = match ($prefix) {
            'b' => [2, '/[01]+/A'],
            'd' => [10, '/[0-9]+/A'],
            'x' => [16, '/[0-9A-Fa-f]+/A'],
        };
        $first = $this->numericValue($pattern, $base);
        if ($this->peek() === '-') {
            ++$this->position;
            $last = $this->numericValue($pattern, $base);
            if ($last < $first) {
                $this->fail('Numeric range is reversed');
            }

            return (new Range($this->character($first), $this->character($last)))->caseSensitive();
        }

        $literal = $this->character($first);
        while ($this->peek() === '.') {
            ++$this->position;
            $literal .= $this->character($this->numericValue($pattern, $base));
        }

        return (new Text($literal))->caseSensitive();
    }

    private function numericValue(string $pattern, int $base): int
    {
        $digits = $this->take($pattern);
        if ($digits === '') {
            $this->fail('Expected numeric terminal');
        }
        $maximum = $this->encoding === SourceMode::BYTES ? 255 : 0x10ffff;
        $value = 0;
        foreach (str_split(strtolower($digits)) as $digit) {
            $value = $value * $base + strpos('0123456789abcdef', $digit);
            if ($value > $maximum) {
                $this->fail('Numeric terminal exceeds the selected encoding');
            }
        }
        if ($this->encoding === SourceMode::UTF8 && $value >= 0xd800 && $value <= 0xdfff) {
            $this->fail('Surrogates are not Unicode scalar values');
        }

        return $value;
    }

    private function character(int $value): string
    {
        return $this->encoding === SourceMode::BYTES ? chr($value) : mb_chr($value, 'UTF-8');
    }
}
