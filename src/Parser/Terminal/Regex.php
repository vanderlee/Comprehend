<?php

namespace Vanderlee\Comprehend\Parser\Terminal;

use InvalidArgumentException;
use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Parser\Parser;

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

        if (@preg_match($pattern, null) === false) {
            throw new InvalidArgumentException('Invalid pattern');
        }

        $this->pattern = $pattern;
    }

    protected function parse(&$input, $offset, Context $context)
    {
        $this->pushCaseSensitivityToContext($context);
        $pattern = $this->pattern . ($context->isCaseSensitive()
                ? ''
                : 'i');
        $this->popCaseSensitivityFromContext($context);

        if (preg_match($pattern, $input, $match, 0, $offset) !== false) {
            if (count($match) > 0 && mb_strlen($match[0]) > 0 && strpos($input, $match[0], $offset) === $offset) {
                return $this->success($input, $offset, mb_strlen($match[0]));
            }
        }

        return $this->failure($input, $offset);
    }

    public function __toString()
    {
        return (string)$this->pattern;
    }
}
