<?php

namespace Vanderlee\Comprehend\Parser\Terminal;

use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * Matches an integer within the specified range
 *
 * @author Martijn
 */
class Integer extends Parser
{
    use CaseSensitiveTrait;

    /**
     * List of digits to use for the different bases (upto 36)
     * @var string
     */
    private static $set = '0123456789abcdefghijklmnopqrstuvwxyz';

    /**
     * @var int|null
     */
    private $minimum;

    /**
     * @var int|null
     */
    private $maximum;

    /**
     * @var int
     */
    private $base;

    public function __construct($minimum = 0, $maximum = null, $base = 10)
    {
        if ($minimum !== null && !is_int($minimum)) {
            throw new \Exception('Minimum must be integer or `null`');
        }

        if ($maximum !== null && !is_int($maximum)) {
            throw new \Exception('Maximum must be integer or `null`');
        }

        if ($minimum !== null && $maximum !== null && $minimum > $maximum) {
            throw new \Exception('Maximum must be greater than minimum');
        }

        $this->minimum = $minimum;
        $this->maximum = $maximum;

        $this->base = intval($base);
        if ($base < 2 || $base > strlen(self::$set)) {
            throw new \Exception('Invalid base');
        }
    }

    protected function parse(&$input, $offset, Context $context)
    {
        $this->pushCaseSensitivityToContext($context);

        // Build pattern
        $set0    = substr(self::$set, 0, $this->base);
        $set1    = substr(self::$set, 1, $this->base - 1);
        $pattern = '/^(?:0|-?[' . $set1 . '][' . $set0 . ']*)/' . ($context->isCaseSensitive() ? '' : 'i');

        $this->popCaseSensitivityFromContext($context);

        if (preg_match($pattern, $input, $match, 0, $offset) === 1) {
            do {
                $integer = intval($match[0], $this->base);
                if (($this->minimum === null || $integer >= $this->minimum)
                    && ($this->maximum === null || $integer <= $this->maximum)
                    && $match[0] !== '-') {
                    return $this->success($input, $offset, mb_strlen($match[0]));
                }

                $match[0] = substr($match[0], 0, -1); // strip off last char
            } while ($match[0] !== '');
        }

        return $this->failure($input, $offset);
    }

    public function __toString()
    {
        return ($this->minimum === null ? '<-INF' : ('[' . $this->minimum)) . ',' . ($this->maximum === null ? 'INF>' : ($this->maximum . ']'));
    }

}
