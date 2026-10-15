<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Terminal;

use InvalidArgumentException;
use RuntimeException;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\SourceMode;
use Vanderlee\Comprehend\Source\Utf8Source;

/**
 * Recognizes a PCRE pattern anchored at the current source offset. Integrates source-mode and
 * boundary checks; literalWindow offers a bounded subject for known literals.
 *
 * @author Martijn
 */
class Regex extends Parser
{
    use CaseSensitiveTrait;

    private string $pattern;
    private ?string $windowLiteral = null;

    public function __construct(string $pattern)
    {
        if (empty($pattern)) {
            throw new InvalidArgumentException('Empty pattern');
        }
        if (@preg_match($pattern, '') === false) {
            throw new InvalidArgumentException('Invalid pattern');
        }

        $this->pattern = $pattern;
    }

    /** Only quoted literals have a proven context-free, finite read window. */
    public static function literalWindow(string $literal): self
    {
        if ($literal === '') {
            throw new InvalidArgumentException('Empty window literal');
        }

        $parser = new self('/\A' . preg_quote($literal, '/') . '/');
        $parser->windowLiteral = $literal;

        return $parser;
    }

    public function __toString(): string
    {
        return (string) $this->pattern;
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        [$pattern, $textMode] = $this->recognitionPattern($session);
        [$subject, $subjectOffset] = $this->recognitionSubject($source, $byteOffset, $session, $textMode);

        $status = @preg_match($pattern, $subject, $match, PREG_OFFSET_CAPTURE, $subjectOffset);
        if ($status === false) {
            throw new RuntimeException('Regex recognition failed: ' . preg_last_error_msg());
        }
        if ($status !== 1 || $match[0][1] !== $subjectOffset || $match[0][0] === '') {
            return $this->failure($source, $byteOffset);
        }

        $length = strlen($match[0][0]);
        if ($textMode && !$session->source()->isUnitBoundary($byteOffset + $length)) {
            throw new InvalidArgumentException('Text regex matched a partial UTF-8 code point');
        }

        return $this->success($source, $byteOffset, $length);
    }

    /** @return array{string, bool} Pattern and whether the source uses UTF-8. */
    private function recognitionPattern(ParseSession $context): array
    {
        $this->pushCaseSensitivityToContext($context);
        try {
            $pattern = $this->pattern . ($context->isCaseSensitive() ? '' : 'i');
        } finally {
            $this->popCaseSensitivityFromContext($context);
        }

        $textMode = $context->source()->mode() === SourceMode::UTF8;
        if (!$textMode && $this->usesUtf8()) {
            throw new InvalidArgumentException('UTF-8 regex cannot recognize a byte source');
        }
        if ($textMode) {
            new Utf8Source($this->pattern);
        }
        if ($textMode && $this->containsByteEscape()) {
            throw new InvalidArgumentException('Text regex cannot use the byte escape \C');
        }

        return [$pattern . ($textMode ? 'u' : ''), $textMode];
    }

    private function usesUtf8(): bool
    {
        if (preg_match('/[a-zA-Z]*$/', $this->pattern, $modifiers) === 1 && str_contains($modifiers[0], 'u')) {
            return true;
        }
        // PCRE permits UTF mode switches before the pattern body as well.
        $body = substr($this->pattern, 1);
        while (preg_match('/^\(\*([A-Z0-9_]+)(?:=[0-9]+)?\)/', $body, $option) === 1) {
            if (in_array($option[1], ['UTF', 'UTF8'], true)) {
                return true;
            }
            $body = substr($body, strlen($option[0]));
        }

        return false;
    }

    private function containsByteEscape(): bool
    {
        $quoted = false;
        for ($index = 0; $index < strlen($this->pattern) - 1; $index++) {
            if ($this->pattern[$index] === '\\') {
                if ($this->pattern[$index + 1] === 'Q' && !$quoted) {
                    $quoted = true;
                } elseif ($this->pattern[$index + 1] === 'E' && $quoted) {
                    $quoted = false;
                } elseif ($this->pattern[$index + 1] === 'C' && !$quoted) {
                    return true;
                }
                $index++;
                // An escaped backslash is literal, not a byte escape.
            }
        }

        return false;
    }

    /** @return array{string, int} Subject text and its PCRE starting offset. */
    private function recognitionSubject(Source $input, int $offset, ParseSession $context, bool $textMode): array
    {
        if ($this->windowLiteral === null) {
            $context->consumeWork(max(1, $input->byteLength()), $offset);

            return [$input->sliceBytes(0, $input->byteLength()), $offset];
        }

        if ($textMode) {
            new Utf8Source($this->windowLiteral);
        }
        $window = $textMode ? 4 * mb_strlen($this->windowLiteral, 'UTF-8') : strlen($this->windowLiteral);
        $end = min($input->byteLength(), $offset + $window);
        while (!$input->isUnitBoundary($end)) {
            --$end;
        }

        $context->consumeWork(max(1, $end - $offset), $offset);

        return [$input->sliceBytes($offset, $end), 0];
    }
}
