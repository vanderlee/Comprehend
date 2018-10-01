<?php

namespace Vanderlee\Comprehend\Parser\Terminal;

use Exception;
use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Match\Failure;
use Vanderlee\Comprehend\Match\Success;
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
     * List of digits to use for the different bases (up to 36)
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

    /**
     * @param int|null $minimum
     * @param int|null $maximum
     * @param int $base
     * @throws Exception
     */
    public function __construct($minimum = 0, $maximum = null, $base = 10)
    {
        if ($minimum !== null
            && $maximum !== null
            && $minimum > $maximum) {

            throw new \Exception('Maximum must be greater than minimum');
        }

        $this->setMinimum($minimum);
        $this->setMaximum($maximum);

        $this->base = intval($base);
        if ($base < 2
            || $base > strlen(self::$set)) {

            throw new \Exception('Unsupported base');
        }
    }

    /**
     * @param int|null $minimum
     * @throws Exception
     */
    private function setMinimum($minimum)
    {
        if ($minimum !== null
            && !is_int($minimum)) {

            throw new Exception('Minimum must be integer or `null`');
        }

        $this->minimum = $minimum;
    }

    /**
     * @param int|null $maximum
     * @throws Exception
     */
    private function setMaximum($maximum)
    {
        if ($maximum !== null
            && !is_int($maximum)) {

            throw new Exception('Maximum must be integer or `null`');
        }

        $this->maximum = $maximum;
    }

    /**
     * @param string $input
     * @param int $offset
     * @param Context $context
     * @return Failure|Success
     */
    protected function parse(&$input, $offset, Context $context)
    {
        $this->pushCaseSensitivityToContext($context);

        // Build pattern
        $set0    = substr(self::$set, 0, $this->base);
        $set1    = substr(self::$set, 1, $this->base - 1);
        $pattern = '/(?:0|-?[' . $set1 . '][' . $set0 . ']*)/A' . ($context->isCaseSensitive()
                ? ''
                : 'i');

        $this->popCaseSensitivityFromContext($context);

        if (preg_match($pattern, $input, $match, 0, $offset) === 1) {
            do {
                $integer = intval($match[0], $this->base);
                if (($this->minimum === null
                        || $integer >= $this->minimum)
                    && ($this->maximum === null
                        || $integer <= $this->maximum)
                    && $match[0] !== '-') {

                    return $this->success($input, $offset, mb_strlen($match[0]));
                }

                $match[0] = substr($match[0], 0, -1); // strip off last char
            } while ($match[0] !== '');
        }

        return $this->failure($input, $offset);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return ($this->minimum === null
                ? '<-INF'
                : ('[' . $this->minimum)) . ',' . ($this->maximum === null
                ? 'INF>'
                : ($this->maximum . ']'));
    }

}
