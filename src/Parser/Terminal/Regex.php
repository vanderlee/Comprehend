<?php

namespace Vanderlee\Comprehend\Parser\Terminal;

use InvalidArgumentException;
use RuntimeException;
use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Source\Utf8Source;

/**
 * Matches regular expressions.
 *
 * @author Martijn
 */
class Regex extends Parser
{
    use CaseSensitiveTrait;

    /**
     * @var string|null
     */
    private $pattern = null;

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

    protected function parse(&$input, $offset, Context $context)
    {
        $this->pushCaseSensitivityToContext($context);

        try {
            $pattern = $this->pattern . ($context->isCaseSensitive()
                    ? ''
                    : 'i');
        } finally {
            $this->popCaseSensitivityFromContext($context);
        }

        if ($context->source() !== null) {
            $textMode = $context->source() instanceof Utf8Source;

            if (!$textMode && $this->usesUtf8()) {
                throw new InvalidArgumentException('UTF-8 regex cannot recognize a byte source');
            }

            if ($textMode) {
                new Utf8Source($this->pattern);
            }

            if ($textMode && $this->containsByteEscape()) {
                throw new InvalidArgumentException('Text regex cannot use the byte escape \\C');
            }

            $pattern .= $textMode ? 'u' : '';

            $subject = $context->source()->sliceBytes(0, $context->source()->byteLength());

            $status = @preg_match($pattern, $subject, $match, PREG_OFFSET_CAPTURE, $offset);
            if ($status === false) {
                throw new RuntimeException('Regex recognition failed: ' . preg_last_error_msg());
            }
            if ($status !== 1 || $match[0][1] !== $offset || $match[0][0] === '') {
                return $this->failure($input, $offset);
            }

            $length = strlen($match[0][0]);

            if (
                $textMode
                && !$context->source()->isCodePointBoundary($offset + $length)
            ) {
                throw new InvalidArgumentException('Text regex matched a partial UTF-8 code point');
            }

            return $this->success($input, $offset, $length);
        }

        if ((preg_match($pattern, $input, $match, 0, $offset) !== false)
            && count($match) > 0
            && mb_strlen($match[0]) > 0
            && strpos($input, $match[0], $offset) === $offset
        ) {
            return $this->success($input, $offset, mb_strlen($match[0]));
        }

        return $this->failure($input, $offset);
    }

    public function __toString()
    {
        return (string)$this->pattern;
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
                $index++; // An escaped backslash is literal, not a byte escape.
            }
        }
        return false;
    }

    private function usesUtf8(): bool
    {
        preg_match('/[a-zA-Z]*$/', $this->pattern, $modifiers);
        if (strpos($modifiers[0], 'u') !== false) {
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
}
