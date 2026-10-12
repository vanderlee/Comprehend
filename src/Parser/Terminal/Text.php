<?php

namespace Vanderlee\Comprehend\Parser\Terminal;

use Vanderlee\Comprehend\Source\Utf8Source;

use InvalidArgumentException;
use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Parser\Parser;

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

    public function __construct($text)
    {
        $this->text = $text;

        $this->length = mb_strlen($text);
        if ($this->length <= 0) {
            throw new InvalidArgumentException('Empty argument');
        }
    }

    protected function parse(&$input, $offset, Context $context)
    {
        $this->pushCaseSensitivityToContext($context);

        try {
            if ($context->source() !== null) {
                $literal = $context->source() instanceof Utf8Source
                    ? mb_str_split((new Utf8Source($this->text))
                        ->sliceBytes(0, strlen($this->text)), 1, 'UTF-8')
                    : str_split($this->text);

                $position = $offset;
                foreach ($literal as $character) {
                    $unit = $context->readSourceUnit($position);
                    if ($unit === null || $context->handleCase($unit->rawText()) !== $context->handleCase($character)) {
                        return $this->failure($input, $offset, $position - $offset);
                    }

                    $position = $unit->end;
                }

                return $this->success($input, $offset, $position - $offset);
            }

            $text = $context->handleCase($this->text);

            for ($c = 0; $c < $this->length; $c++) {
                if ($offset + $c >= mb_strlen($input) || $text[$c] != $context->handleCase($input[$offset + $c])) {
                    return $this->failure($input, $offset, $c);
                }
            }
        } finally {
            $this->popCaseSensitivityFromContext($context);
        }

        return $this->success($input, $offset, $this->length);
    }

    public function __toString()
    {
        return '"' . $this->text . '"';
    }
}
