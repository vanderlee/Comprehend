<?php

declare (strict_types=1);

namespace Vanderlee\Comprehend\Parser\Terminal;

use InvalidArgumentException;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\SourceMode;
use Vanderlee\Comprehend\Source\Utf8Source;

/**
 * Description of TextParser.
 *
 * @author Martijn
 */
class Text extends Parser
{
    use CaseSensitiveTrait;

    private $text;
    private $length;

    public function __construct(string $text)
    {
        $this->text = $text;
        $this->length = strlen($text);
        if ($this->length <= 0) {
            throw new InvalidArgumentException('Empty argument');
        }
    }

    public function __toString(): string
    {
        return '"' . $this->text . '"';
    }

    protected function recognize(Source $input, int $offset, ParseSession $context): AbstractMatch
    {
        $this->pushCaseSensitivityToContext($context);
        try {
            $literal = $context->source()->mode() === SourceMode::UTF8 ? mb_str_split(
                (new Utf8Source($this->text))->sliceBytes(0, strlen($this->text)),
                1,
                'UTF-8',
            ) : str_split($this->text);
            $position = $offset;
            foreach ($literal as $character) {
                $unit = $context->readSourceUnit($position);
                if ($unit === null || $context->handleCase($unit->rawText()) !== $context->handleCase($character)) {
                    return $this->failure($input, $offset, $position - $offset);
                }
                $position = $unit->end;
            }
            return $this->success($input, $offset, $position - $offset);
        } finally {
            $this->popCaseSensitivityFromContext($context);
        }
    }
}
