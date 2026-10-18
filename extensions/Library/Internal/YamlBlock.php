<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Library\Internal;

use Vanderlee\Comprehend\Builder\AnalyzableParser;
use Vanderlee\Comprehend\Builder\DefaultAnalysisTrait;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Builder\NullabilityLookup;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/** YAML block indentation and stream framing; flow nodes delegate to the ordinary graph. @internal */
final class YamlBlock extends Parser implements AnalyzableParser
{
    use DefaultAnalysisTrait;

    public function __construct(
        private Parser $flow,
        private bool $stream = false,
        private ?int $indent = null,
        private ?int $parentIndent = null,
        private ?int $origin = null,
    ) {
        // Nested blocks share the frozen flow graph and carry their own indentation.
    }

    public function __toString(): string
    {
        return $this->stream ? 'YAML stream' : 'YAML block node';
    }

    public function analysisNullability(NullabilityLookup $lookup): bool
    {
        return $this->stream;
    }

    public function children(): array
    {
        return [$this->flow];
    }

    protected function copyDependencies(GraphSnapshot $context): void
    {
        parent::copyDependencies($context);
        $this->flow = $context->copy($this->flow);
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        if (!$this->stream) {
            if ($this->origin === null) {
                return (new self(
                    $this->flow,
                    indent: $this->indent,
                    parentIndent: $this->parentIndent,
                    origin: $byteOffset,
                ))->recognizeInSession($source, $byteOffset, $session);
            }
            return $this->block($source, $byteOffset, $session);
        }
        $text = $source->sliceBytes($byteOffset, $source->byteLength());
        $session->consumeWork(max(1, strlen($text)), $byteOffset);
        if (!YamlCharacters::valid($text)) {
            return $this->failure($source, $byteOffset);
        }
        $position = $byteOffset + (str_starts_with($text, "\xef\xbb\xbf") ? 3 : 0);
        $children = [];
        $needsStart = false;
        $seenDocument = false;
        $yamlDirective = false;
        $pendingHandles = [];
        $handles = ['!', '!!'];
        while ($position < $source->byteLength()) {
            $position = $this->skipEmpty($source, $position, $session);
            if ($position >= $source->byteLength()) {
                break;
            }
            [$line, $end] = $this->line($source, $position);
            if (str_starts_with($line, '%')) {
                $isYaml = preg_match('/\A%YAML(?:[ \t]|$)/', $line) === 1;
                $isTag = preg_match('/\A%TAG(?:[ \t]|$)/', $line) === 1;
                $directive = $isYaml ? '/\A%YAML[ \t]+1\.[0-9]+(?:[ \t]+#.*)?[ \t]*\z/'
                    : ($isTag ? '/\A%TAG[ \t]+!\S*[ \t]+\S+(?:[ \t]+#.*)?[ \t]*\z/'
                        : '/\A%[A-Za-z0-9-]+(?:[ \t]+\S+)*(?:[ \t]+#.*)?[ \t]*\z/');
                if (
                    preg_match($directive, $line) !== 1 || $seenDocument
                    || ($isYaml && $yamlDirective)
                ) {
                    return $this->failure($source, $byteOffset, $position - $byteOffset);
                }
                $yamlDirective = $yamlDirective || $isYaml;
                if (preg_match('/\A%TAG[ \t]+(![^ \t]*)/', $line, $tag) === 1) {
                    if (in_array($tag[1], $pendingHandles, true)) {
                        return $this->failure($source, $byteOffset, $position - $byteOffset);
                    }
                    $pendingHandles[] = $tag[1];
                }
                $needsStart = true;
                $position = $end;
                continue;
            }
            if (preg_match('/\A(?:---|\.\.\.)(?:[ \t]|$)/', $line) === 1) {
                if (str_starts_with($line, '...') && $needsStart) {
                    return $this->failure($source, $byteOffset, $position - $byteOffset);
                }
                $needsStart = false;
                $yamlDirective = false;
                $seenDocument = str_starts_with($line, '---');
                $handles = array_merge(['!', '!!'], $pendingHandles);
                $pendingHandles = [];
                $inline = ltrim(substr($line, 3), " \t");
                if ($inline !== '' && $inline[0] !== '#') {
                    if (!$seenDocument) {
                        return $this->failure($source, $byteOffset, $position - $byteOffset);
                    }
                    if ($this->mappingColon($inline) !== null || preg_match('/\A[-?](?:[ \t]|$)/', $inline)) {
                        return $this->failure($source, $byteOffset, $position - $byteOffset);
                    }
                    $inlineOffset = $position + strlen($line) - strlen($inline);
                    $child = (new self($this->flow, indent: 0, origin: $byteOffset))
                        ->recognizeInSession($source, $inlineOffset, $session);
                    if (!$child instanceof Success || $child->length === 0) {
                        return $this->failure($source, $byteOffset, $inlineOffset - $byteOffset);
                    }
                    $children[] = $child;
                    $documentText = $source->sliceBytes($inlineOffset, $inlineOffset + $child->length);
                    if (!$this->validHandles($documentText, $handles)) {
                        return $this->failure($source, $byteOffset, $inlineOffset - $byteOffset);
                    }
                    $position = $inlineOffset + $child->length;
                    continue;
                }
                $position = $end;
                continue;
            }
            if ($needsStart) {
                return $this->failure($source, $byteOffset, $position - $byteOffset);
            }
            // The child enters through the session, so depth/work limits and CST
            // source ownership apply equally to nested blocks and flow expressions.
            $child = (new self($this->flow, origin: $byteOffset))->recognizeInSession($source, $position, $session);
            if (!$child instanceof Success || $child->length === 0) {
                return $this->failure($source, $byteOffset, $position - $byteOffset + $child->length);
            }
            $children[] = $child;
            if (!$this->validHandles($source->sliceBytes($position, $position + $child->length), $handles)) {
                return $this->failure($source, $byteOffset, $position - $byteOffset);
            }
            $position += $child->length;
            $seenDocument = true;
            $position = $this->skipEmpty($source, $position, $session);
            if ($position < $source->byteLength()) {
                [$next] = $this->line($source, $position);
                if (!preg_match('/\A(?:---|\.\.\.)(?:[ \t]|$)/', $next)) {
                    return $this->failure($source, $byteOffset, $position - $byteOffset);
                }
            }
        }

        return $needsStart ? $this->failure($source, $byteOffset, $position - $byteOffset)
            : $this->success($source, $byteOffset, $position - $byteOffset, $children);
    }

    private function block(Source $source, int $offset, ParseSession $session): AbstractMatch
    {
        [$first] = $this->line($source, $offset);
        $indent = $this->indent ?? strspn($first, ' ');
        $position = $offset;
        $firstLine = true;
        $kind = null;
        $children = [];
        while ($position < $source->byteLength()) {
            $position = $this->skipEmpty($source, $position, $session);
            [$line, $end] = $this->line($source, $position);
            $spaces = strspn($line, ' ');
            $actualIndent = $firstLine && $this->indent !== null ? $indent : $spaces;
            $firstLine = false;
            $content = substr($line, $spaces);
            if ($line === '' || $actualIndent < $indent || preg_match('/\A(?:---|\.\.\.)(?:[ \t]|$)/', $line)) {
                break;
            }
            if ($actualIndent > $indent) {
                return $this->failure($source, $offset, $position - $offset);
            }
            if (str_starts_with($content, "\t")) {
                $plainContinuation = $spaces > 0 && $this->mappingColon(ltrim($content, "\t")) === null
                    && preg_match('/\A\t+[-?](?:[ \t]|$)/', $content) !== 1;
                if ($kind !== null || (!$plainContinuation && preg_match('/\A\t+[\[{]/', $content) !== 1)) {
                    return $this->failure($source, $offset, $position - $offset);
                }
                $tabs = strspn($content, "\t");
                $spaces += $tabs;
                $content = substr($content, $tabs);
            }
            $session->consumeWork(max(1, strlen($line)), $position);
            $valueOffset = $position + $spaces;
            $sequence = preg_match('/\A-(?:[ \t]|$)/', $content) === 1;
            $explicit = preg_match('/\A\?(?:[ \t]|$)/', $content) === 1;
            $colon = $this->mappingColon($content);
            $currentKind = $sequence ? 'sequence' : (($explicit || $colon !== null) ? 'mapping' : 'scalar');
            if ($kind !== null && $currentKind !== $kind) {
                break;
            }
            $kind = $currentKind;
            if ($sequence || $explicit) {
                $valueOffset += 1 + strspn(substr($content, 1), " \t");
            } elseif ($colon !== null) {
                $key = rtrim(substr($content, 0, $colon), " \t");
                if ($key !== '') {
                    $keyProperties = $this->propertyLength($key);
                    if ($keyProperties > 0 && str_starts_with(substr($key, $keyProperties), '*')) {
                        return $this->failure($source, $offset, $valueOffset - $offset);
                    }
                    $keyMatch = $this->flow->recognizeInSession($source, $valueOffset, $session);
                    if ($keyMatch instanceof Success && $keyMatch->length === strlen($key)) {
                        $children[] = $keyMatch;
                    } elseif (!$this->validPlain($key)) {
                        return $this->failure($source, $offset, $valueOffset - $offset);
                    }
                    if (strlen($key) > 1024) {
                        return $this->failure($source, $offset, $valueOffset - $offset);
                    }
                }
                $valueOffset += $colon + 1 + strspn(substr($content, $colon + 1), " \t");
            }
            $parentIndent = $kind === 'scalar' ? ($this->parentIndent ?? $indent - 1) : $indent;
            $value = $source->sliceBytes($valueOffset, $position + strlen($line));
            $propertyLength = $this->propertyLength($value);
            $scalarOffset = $valueOffset + $propertyLength;
            $scalarValue = substr($value, $propertyLength);
            if ($propertyLength > 0 && str_starts_with($scalarValue, '*')) {
                return $this->failure($source, $offset, $scalarOffset - $offset);
            }
            if ($scalarValue !== '' && ($scalarValue[0] === '|' || $scalarValue[0] === '>')) {
                if (preg_match('/\A[|>](?:[1-9][+-]?|[+-][1-9]?|)(?:[ \t]+(?:#.*)?)?\z/', $scalarValue) !== 1) {
                    return $this->failure($source, $offset, $scalarOffset - $offset);
                }
                $scalar = $this->blockScalar($source, $end, $parentIndent, $scalarValue, $session);
                if ($scalar === null) {
                    return $this->failure($source, $offset, $scalarOffset - $offset);
                }
                $position = $scalar;
            } elseif ($scalarValue === '' || str_starts_with($scalarValue, '#')) {
                $position = $end;
                $next = $this->skipEmpty($source, $position, $session);
                [$nested] = $this->line($source, $next);
                $nestedIndent = strspn($nested, ' ');
                $indentless = !$sequence && preg_match('/\A-(?:[ \t]|$)/', $nested) === 1;
                if (
                    $nested !== '' && ($nestedIndent > $parentIndent || $indentless
                    || ($propertyLength > 0 && $kind === 'scalar' && $nestedIndent === $indent))
                ) {
                    if ($propertyLength > 0 && $this->mappingColon(ltrim($nested)) === null) {
                        $nestedProperties = substr(ltrim($nested), 0, $this->propertyLength(ltrim($nested)));
                        $ownProperties = substr($value, 0, $propertyLength);
                        if (
                            (str_contains($ownProperties, '&') && str_contains($nestedProperties, '&'))
                            || (str_contains($ownProperties, '!') && str_contains($nestedProperties, '!'))
                        ) {
                            return $this->failure($source, $offset, $next - $offset);
                        }
                    }
                    $match = (new self($this->flow, parentIndent: $parentIndent, origin: $this->origin))
                        ->recognizeInSession($source, $next, $session);
                    if (!$match instanceof Success) {
                        return $this->failure($source, $offset, $next - $offset + $match->length);
                    }
                    $children[] = $match;
                    $position = $next + $match->length;
                }
            } elseif (
                ($sequence || $explicit || ($colon === 0))
                && (preg_match('/\A[-?](?:[ \t]|$)/', $scalarValue) === 1 || $this->mappingColon($scalarValue) !== null)
            ) {
                if ($source->sliceBytes(max(0, $valueOffset - 1), $valueOffset) === "\t") {
                    return $this->failure($source, $offset, $valueOffset - $offset);
                }
                $column = $this->column($source, $valueOffset);
                $match = (new self($this->flow, indent: $column, origin: $this->origin))
                    ->recognizeInSession($source, $valueOffset, $session);
                if (!$match instanceof Success) {
                    return $this->failure($source, $offset, $valueOffset - $offset + $match->length);
                }
                $children[] = $match;
                $position = $valueOffset + $match->length;
            } elseif ($this->isPlain($scalarValue)) {
                if (!$this->validPlain($scalarValue)) {
                    return $this->failure($source, $offset, $scalarOffset - $offset);
                }
                $position = $end;
                $hasComment = preg_match('/(?:^|[ \t])#/', $scalarValue) === 1;
                while (!$hasComment && $position < $source->byteLength()) {
                    [$continuation, $continuationEnd] = $this->line($source, $position);
                    if (preg_match('/\A[ \t]*(?:#.*)?\z/', $continuation) === 1) {
                        if (str_contains($continuation, '#')) {
                            break;
                        }
                        $position = $continuationEnd;
                        continue;
                    }
                    if (
                        preg_match('/\A(?:---|\.\.\.)(?:[ \t]|$)/', $continuation)
                        || strspn($continuation, ' ') <= $parentIndent
                    ) {
                        break;
                    }
                    $trimmed = ltrim($continuation, " \t");
                    if ($this->mappingColon($trimmed) !== null) {
                        return $this->failure($source, $offset, $position - $offset);
                    }
                    $hasComment = preg_match('/(?:^|[ \t])#/', $trimmed) === 1;
                    $session->consumeWork(max(1, strlen($continuation)), $position);
                    $position = $continuationEnd;
                }
            } else {
                $match = $this->flow->recognizeInSession($source, $valueOffset, $session);
                if (!$match instanceof Success) {
                    return $this->failure($source, $offset, $valueOffset - $offset + $match->length);
                }
                $children[] = $match;
                $matchedText = $source->sliceBytes($valueOffset, $valueOffset + $match->length);
                if (preg_match('/(?:\r\n|\r|\n)(?:---|\.\.\.)(?:[ \t\r\n]|$)/', $matchedText)) {
                    return $this->failure($source, $offset, $valueOffset - $offset);
                }
                $lines = preg_split('/\r\n|\r|\n/', $matchedText);
                if ($lines !== false) {
                    foreach (array_slice($lines, 1) as $continued) {
                        if (trim($continued) !== '' && strspn($continued, ' ') <= $parentIndent) {
                            return $this->failure($source, $offset, $valueOffset - $offset);
                        }
                    }
                }
                $after = $valueOffset + $match->length;
                [$tail, $position] = $this->line($source, $after);
                if (preg_match('/\A(?:[ \t]+(?:#.*)?)?\z/', $tail) !== 1) {
                    return $this->failure($source, $offset, $after - $offset);
                }
            }
            if ($explicit) {
                $position = $this->skipEmpty($source, $position, $session);
                // The following ':' line is processed as a mapping entry with an
                // empty key; the explicit key's syntax is already retained above.
            }
            if ($kind === 'scalar') {
                break;
            }
        }

        return $position > $offset ? $this->success($source, $offset, $position - $offset, $children)
            : $this->failure($source, $offset);
    }

    private function blockScalar(
        Source $source,
        int $position,
        int $parent,
        string $header,
        ParseSession $session,
    ): ?int {
        preg_match('/[1-9]/', $header, $indicator);
        $required = isset($indicator[0]) ? max(0, $parent) + (int) $indicator[0] : null;
        $leadingBlank = 0;
        while ($position < $source->byteLength()) {
            [$line, $end] = $this->line($source, $position);
            $indent = strspn($line, ' ');
            if ($required === null && trim($line, " \t") === '' && str_contains($line, "\t") && $indent <= $parent) {
                return null;
            }
            if (trim($line, ' ') === '') {
                if ($required === null) {
                    $leadingBlank = max($leadingBlank, $indent);
                }
                $position = $end;
                continue;
            }
            if ($indent <= $parent || preg_match('/\A(?:---|\.\.\.)(?:[ \t]|$)/', $line)) {
                break;
            }
            if ($required === null) {
                $required = $indent;
                if ($leadingBlank > $required) {
                    return null;
                }
            }
            if ($indent < $required) {
                if (str_starts_with(ltrim($line, ' '), '#')) {
                    break;
                }
                return null;
            }
            $session->consumeWork(max(1, strlen($line)), $position);
            $position = $end;
        }

        return $position;
    }

    private function isPlain(string $text): bool
    {
        return $text !== '' && !str_contains("[{'\"*&!", $text[0]);
    }

    private function validPlain(string $text): bool
    {
        return $text !== '' && preg_match('/\A(?:[\[\]{},&*!|>\'"%@`#]|[-?:](?:[ \t]|$))/', $text) !== 1
            && $this->mappingColon($text) === null;
    }

    private function propertyLength(string $text): int
    {
        $property = '(?:&[^\s\[\]{},]+|!(?:<[^\s<>]+>|[^\s\[\]{},]*))';
        preg_match('/\A' . $property . '(?:[ \t]+' . $property . ')?[ \t]*/', $text, $match);

        return isset($match[0]) ? strlen($match[0]) : 0;
    }

    private function column(Source $source, int $offset): int
    {
        $prefix = $source->sliceBytes(0, $offset);
        $newline = strrpos($prefix, "\n");

        $lineStart = max($this->origin ?? 0, $newline === false ? 0 : $newline + 1);

        return $offset - $lineStart;
    }

    /** @param list<string> $handles */
    private function validHandles(string $text, array $handles): bool
    {
        preg_match_all('/(?:\A|\r\n|\r|\n|[:\[,])[ \t]*(?:[-?:][ \t]+)?(![A-Za-z0-9-]+!)/', $text, $matches);
        foreach ($matches[1] as $handle) {
            if (!in_array($handle, $handles, true)) {
                return false;
            }
        }

        return true;
    }


    /** @return array{string, int} */
    private function line(Source $source, int $offset): array
    {
        $remaining = $source->sliceBytes($offset, $source->byteLength());
        $length = strcspn($remaining, "\r\n");
        $end = $offset + $length;
        if (substr($remaining, $length, 2) === "\r\n") {
            $end += 2;
        } elseif ($end < $source->byteLength()) {
            $end++;
        }

        return [substr($remaining, 0, $length), $end];
    }

    private function skipEmpty(Source $source, int $offset, ParseSession $session): int
    {
        while ($offset < $source->byteLength()) {
            [$line, $end] = $this->line($source, $offset);
            if (preg_match('/\A[ \t]*(?:#.*)?\z/', $line) !== 1) {
                break;
            }
            $session->consumeWork(max(1, $end - $offset), $offset);
            $offset = $end;
        }

        return $offset;
    }

    private function mappingColon(string $text): ?int
    {
        $quote = null;
        $depth = 0;
        $start = $this->propertyLength($text);
        if (($text[$start] ?? '') === '*') {
            $start += strcspn($text, " \t", $start);
        }
        $structured = isset($text[$start]) && str_contains('[{"\'', $text[$start]);
        for ($index = $start; $index < strlen($text); $index++) {
            $char = $text[$index];
            if ($quote !== null) {
                if ($quote === '"' && $char === '\\') {
                    $index++;
                } elseif ($char === $quote) {
                    if ($quote === "'" && ($text[$index + 1] ?? '') === "'") {
                        $index++;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }
            if ($char === '#' && $index > 0 && ($text[$index - 1] === ' ' || $text[$index - 1] === "\t")) {
                break;
            }
            if ($structured && ($char === '"' || $char === "'")) {
                $quote = $char;
            } elseif ($structured && ($char === '[' || $char === '{')) {
                $depth++;
            } elseif ($structured && ($char === ']' || $char === '}')) {
                $depth--;
            } elseif (
                $char === ':' && $depth === 0 && (!isset($text[$index + 1])
                || $text[$index + 1] === ' ' || $text[$index + 1] === "\t")
            ) {
                return $index;
            }
        }

        return null;
    }
}
